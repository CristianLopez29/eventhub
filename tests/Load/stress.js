import http from 'k6/http';
import { check } from 'k6';

export const options = {
    stages: [
        { duration: '5s', target: 5 },
        { duration: '20s', target: 5 },
        { duration: '5s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<50'],
        http_req_failed: ['rate<0.01'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export function setup() {
    const loginRes = http.post(
        `${BASE_URL}/login`,
        JSON.stringify({ username: 'admin', password: 'adminpass' }),
        { headers: { 'Content-Type': 'application/json' } }
    );

    const token = loginRes.json('token');

    const startsAt = '2024-06-01T00:00:00';
    const endsAt = '2024-06-30T23:59:59';
    const url = `${BASE_URL}/events?starts_at=${startsAt}&ends_at=${endsAt}`;

    for (let i = 0; i < 5; i++) {
        http.get(url, {
            headers: { Authorization: `Bearer ${token}` },
        });
    }

    return { token };
}

export default function (data) {
    const startsAt = '2024-06-01T00:00:00';
    const endsAt = '2024-06-30T23:59:59';
    const url = `${BASE_URL}/events?starts_at=${startsAt}&ends_at=${endsAt}`;

    const response = http.get(url, {
        headers: { Authorization: `Bearer ${data.token}` },
    });

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response has data': (r) => {
            if (r.status !== 200) return false;
            const body = JSON.parse(r.body);
            return body.data !== undefined && Array.isArray(body.data.events);
        },
        'response time < 50ms': (r) => r.timings.duration < 50,
    });
}
