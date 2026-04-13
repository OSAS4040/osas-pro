<?php
// Put this at the very start of public/index.php temporarily - NO, put as a separate endpoint
// Test what REQUEST_URI nginx passes
header('Content-Type: application/json');
echo json_encode([
    'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? 'missing',
    'PATH_INFO'      => $_SERVER['PATH_INFO'] ?? 'missing',
    'SCRIPT_NAME'    => $_SERVER['SCRIPT_NAME'] ?? 'missing',
    'HTTP_HOST'      => $_SERVER['HTTP_HOST'] ?? 'missing',
]);
