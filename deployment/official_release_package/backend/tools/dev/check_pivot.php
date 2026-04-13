<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$db = app('db');
$cols = $db->select("SELECT column_name FROM information_schema.columns WHERE table_name='model_has_roles' ORDER BY ordinal_position");
echo "model_has_roles columns: " . implode(', ', array_column($cols, 'column_name')) . "\n";
$cols2 = $db->select("SELECT column_name FROM information_schema.columns WHERE table_name='model_has_permissions' ORDER BY ordinal_position");
echo "model_has_permissions columns: " . implode(', ', array_column($cols2, 'column_name')) . "\n";
