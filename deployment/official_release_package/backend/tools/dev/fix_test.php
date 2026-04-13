<?php
$f = file_get_contents('/var/www/test_final.php');
$f = str_replace(
    "test('Fuel',api('GET',\"\$base/governance/fuel\",null,\$ot)['status']===200);",
    "test('Fuel',api('GET',\"$base/governance/fuel\",null,\$ot)['status']===200);",
    $f
);
file_put_contents('/var/www/test_final.php', $f);
echo "Done\n";
echo "Verify: ";
preg_match('/test..Fuel.*/', $f, $m);
echo $m[0] ?? 'NOT FOUND';
echo "\n";
