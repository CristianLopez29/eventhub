<?php

declare(strict_types=1);

$mode = $_ENV['PROVIDER_MODE'] ?? 'static';

$uri = $_SERVER['REQUEST_URI'] ?? '/';

if ($uri !== '/events.xml') {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Not Found</error>';
    exit;
}

header('Content-Type: application/xml');

if ($mode === 'dynamic') {
    require __DIR__ . '/generator.php';
    echo generateDynamicXml();
    exit;
}

// Static mode serves one stable catalogue: every base_plan from every resources/response_*.xml
// merged into a single planList. The files are three snapshots of the same feed plus the
// wider catalogue in _4/_5; a duplicated base_plan_id across snapshots is harmless because
// the consumer keys events by plan_id and the glob is sorted, so the last file wins
// deterministically. Feed churn and malformed data are the `dynamic` mode's job.
$files = glob(__DIR__ . '/resources/response_*.xml');

if ($files === false || $files === []) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>No XML files available</error>';
    exit;
}

sort($files);

$catalogue = new DOMDocument('1.0', 'UTF-8');
$catalogue->loadXML('<planList version="1.0"><output/></planList>');
$output = $catalogue->getElementsByTagName('output')->item(0);

foreach ($files as $file) {
    $snapshot = new DOMDocument();

    if (@$snapshot->load($file) === false) {
        continue;
    }

    foreach ($snapshot->getElementsByTagName('base_plan') as $basePlan) {
        $output->appendChild($catalogue->importNode($basePlan, true));
    }
}

echo $catalogue->saveXML();
