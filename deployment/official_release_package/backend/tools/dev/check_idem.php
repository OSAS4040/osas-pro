<?php
// Check the actual structure of the route cache
$cache = require '/var/www/bootstrap/cache/routes-v7.php';
$routes = $cache['routes'] ?? $cache;
$uris = [];
foreach ($routes as $method => $methodRoutes) {
    if (is_array($methodRoutes)) {
        foreach ($methodRoutes as $uri => $r) {
            if (str_contains($uri, 'governance')) {
                $uris[] = strtoupper($method) . ' ' . $uri;
            }
        }
    }
}
echo count($uris) . " governance routes in cache:\n";
echo implode("\n", $uris) . "\n";
