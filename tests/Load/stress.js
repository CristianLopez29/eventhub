import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';
import { htmlReport } from 'https://cdn.jsdelivr.net/gh/benc-uk/k6-reporter@2.4.0/dist/bundle.js';

const BASE_URL = __ENV.BASE_URL || 'http://nginx';
const USERNAME = __ENV.API_USERNAME || 'admin';
const PASSWORD = __ENV.API_PASSWORD || 'secret123';
const STARTS_AT = __ENV.STARTS_AT || '2024-01-01T00:00:00';
const ENDS_AT = __ENV.ENDS_AT || '2024-12-31T23:59:59';

const searchDuration = new Trend('search_duration', true);
const serverErrors = new Counter('server_errors');

export const options = {
    stages: [
        { duration: '5s', target: 5 },
        { duration: '20s', target: 5 },
        { duration: '5s', target: 0 },
    ],
    thresholds: {
        // The gate: requests answer, and none of them 5xx.
        http_req_failed: ['rate<0.01'],
        server_errors: ['count==0'],
        // Latency is reported, not gated: the stack under test runs APP_ENV=dev behind a
        // bind mount, which dominates the number. See tests/Load/README.md.
        search_duration: ['p(95)<1000'],
    },
};

function searchUrl() {
    return `${BASE_URL}/events?starts_at=${STARTS_AT}&ends_at=${ENDS_AT}`;
}

export function setup() {
    const loginResponse = http.post(
        `${BASE_URL}/login`,
        JSON.stringify({ username: USERNAME, password: PASSWORD }),
        { headers: { 'Content-Type': 'application/json' } }
    );

    // Failing loudly here is the point: the previous version logged in with the wrong
    // password, got null, and then measured 2650 unauthenticated 401s as if they were
    // search latency.
    if (loginResponse.status !== 200) {
        throw new Error(
            `Login failed with status ${loginResponse.status}. ` +
            `Check API_USERNAME / API_PASSWORD (default admin / secret123).`
        );
    }

    const token = loginResponse.json('data.token');

    if (!token) {
        throw new Error('Login succeeded but no token was found at data.token.');
    }

    // Warm the search cache so the run measures steady state rather than cold misses.
    const headers = { Authorization: `Bearer ${token}` };
    for (let warmup = 0; warmup < 5; warmup += 1) {
        http.get(searchUrl(), { headers });
    }

    return { token };
}

export default function (data) {
    const response = http.get(searchUrl(), {
        headers: { Authorization: `Bearer ${data.token}` },
        tags: { name: 'search_events' },
    });

    searchDuration.add(response.timings.duration);

    if (response.status >= 500) {
        serverErrors.add(1);
    }

    check(response, {
        'status is 200': (r) => r.status === 200,
        'body carries the envelope': (r) => {
            if (r.status !== 200) {
                return false;
            }
            const body = JSON.parse(r.body);
            return body.error === null && Array.isArray(body.data.events);
        },
        'pagination meta is present': (r) => {
            if (r.status !== 200) {
                return false;
            }
            return typeof JSON.parse(r.body).data.meta.total === 'number';
        },
    });
}

// Writes the console summary and an HTML report next to the script (tests/Load/results/
// when run through `make load-test`), so the numbers in the README have their source
// committed beside them. The two imports at the top are fetched from a CDN at start, so a
// run needs outbound network the same way pulling the grafana/k6 image does.
export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: ' ', enableColors: true }),
        'summary.txt': textSummary(data, { indent: ' ', enableColors: false }),
        'report.html': htmlReport(data),
    };
}
