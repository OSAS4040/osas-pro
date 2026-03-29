<?php
$base = 'http://172.19.0.8/api/v1';

function req($method, $url, $data=null, $token=null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer '.$token;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER    => $headers,
        CURLOPT_RETURNTRANSFER=> 1,
        CURLOPT_TIMEOUT       => 10,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code, 'body'=>json_decode($body,true), 'raw'=>$body];
}

$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'password']);
echo "Login owner HTTP {$r['code']}\n";
echo substr($r['raw'], 0, 300) . "\n";
$token = $r['body']['token'] ?? '';

if ($token) {
    $r2 = req('GET', "$base/contracts", null, $token);
    echo "GET contracts HTTP {$r2['code']}\n";
    echo substr($r2['raw'], 0, 300) . "\n";
}
