<?php
// Test connectivity
$hosts = [
    'nginx (172.19.0.8)'   => 'http://172.19.0.8/api/v1/health',
    'saas_nginx hostname'  => 'http://saas_nginx/api/v1/health',
    'localhost'            => 'http://localhost/api/v1/health',
    '127.0.0.1'            => 'http://127.0.0.1/api/v1/health',
];

foreach ($hosts as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo "$label => HTTP $code" . ($err ? " ERR: $err" : "") . "\n";
    if ($code > 0) echo "  Response: " . substr($body, 0, 100) . "\n";
}
