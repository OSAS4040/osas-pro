<?php

/** Scan tail of huge laravel.log (byte window) for POS / SQLSTATE lines. */
$path = __DIR__.'/../storage/logs/laravel.log';
if (! is_readable($path)) {
    fwrite(STDERR, "Cannot read: {$path}\n");
    exit(1);
}
$size = filesize($path);
$window = isset($argv[1]) ? (int) $argv[1] : 3_000_000;
$window = min($window, $size);
$fh = fopen($path, 'rb');
fseek($fh, -$window, SEEK_END);
$chunk = fread($fh, $window);
fclose($fh);

$re = '/POS_FAILURE|SQLSTATE|LedgerPostingFailedException|duplicate key|23505|POS_SALE_COMPLETED|POS_SALE_ENTERED|invoice_number/i';
$hits = 0;
foreach (explode("\n", $chunk) as $line) {
    if (preg_match($re, $line)) {
        echo $line."\n";
        $hits++;
    }
}
if ($hits === 0) {
    fwrite(STDERR, "(no line matches in last {$window} bytes; chunk_len=".strlen($chunk).")\n");
    foreach (['SQLSTATE', 'POS_FAILURE', 'LedgerPostingFailed', 'pos/sale', 'POS_SALE'] as $needle) {
        $p = stripos($chunk, $needle);
        if ($p !== false) {
            echo "--- first {$needle} ---\n".substr($chunk, max(0, $p - 100), 500)."\n";
        }
    }
}
