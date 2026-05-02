<?php

use App\Models\User;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = User::where('email', 'owner@demo.sa')->first();
$token = $user->createToken('test2')->plainTextToken;
echo $token;
