<?php
// Get a fresh token first via DB
use App\Models\User;
$user = User::find(1);
$token = $user->createToken('test')->plainTextToken;

$ch = curl_init('http://172.19.0.2/api/v1/products');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json', "Authorization: Bearer $token"],
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $status\n";
echo substr($body, 0, 1000) . "\n";
