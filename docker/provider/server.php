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
} else {
    $files = glob(__DIR__ . '/resources/response_*.xml');

    if ($files === false || $files === []) {
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>No XML files available</error>';
        exit;
    }

    sort($files);
    $selected = $files[array_rand($files)];
    $content = file_get_contents($selected);

    if ($content === false) {
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>Failed to read XML</error>';
        exit;
    }

    echo $content;
}
