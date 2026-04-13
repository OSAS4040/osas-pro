<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$wallets = App\Models\Wallet::where('company_id', $user->company_id)->with('customer')->paginate(20);
echo json_encode(['wallets' => $wallets], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
