<?php
$path = '/var/www/.env';
$content = file_get_contents($path);

$replacements = [
    'DB_DATABASE=osas_db'  => 'DB_DATABASE=saas_db',
    'DB_USERNAME=postgres' => 'DB_USERNAME=saas_user',
    'DB_PASSWORD=secret'   => 'DB_PASSWORD=saas_password',
    'LOG_LEVEL=debug'      => 'LOG_LEVEL=warning',
];

foreach ($replacements as $from => $to) {
    if (strpos($content, $from) !== false) {
        $content = str_replace($from, $to, $content);
        echo "Fixed: $from => $to\n";
    }
}

file_put_contents($path, $content);
echo "Done. New .env values:\n";
echo shell_exec("grep -E 'DB_|LOG_LEVEL|APP_KEY' /var/www/.env") ?? '';
