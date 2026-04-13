<?php
$content = file_get_contents('/var/www/test_final.php');
// The broken line has escaped $base
// Replace \$base with actual $base interpolation by using concatenation
$old = "test('Fuel',api('GET',\"\\\$base/governance/fuel\",null,\$ot)['status']===200);";
$new = "test('Fuel',api('GET',\$base.\"/governance/fuel\",null,\$ot)['status']===200);";
echo "Old pattern match: " . (strpos($content, $old) !== false ? "YES" : "NO") . "\n";
// Try direct approach
$lines = explode("\n", $content);
foreach ($lines as &$line) {
    if (strpos($line, "Fuel") !== false && strpos($line, "governance/fuel") !== false) {
        echo "Found: $line\n";
        $line = "test('Fuel',api('GET',\$base.\"/governance/fuel\",null,\$ot)['status']===200);";
        echo "Fixed: $line\n";
    }
}
$newContent = implode("\n", $lines);
file_put_contents('/var/www/test_final.php', $newContent);
echo "Saved\n";
