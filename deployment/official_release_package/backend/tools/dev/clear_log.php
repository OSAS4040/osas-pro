<?php
file_put_contents('/var/www/storage/logs/laravel.log', '');
echo "Log cleared. Size: " . filesize('/var/www/storage/logs/laravel.log') . " bytes\n";
