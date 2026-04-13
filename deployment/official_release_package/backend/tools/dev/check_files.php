$errors = [];
$models = ["User","Vehicle","Invoice","WorkOrder","Employee","Fleet","Contract","Wallet","JournalEntry"];
foreach($models as $m) {
    $file = "/var/www/app/Models/" . $m . ".php";
    if(!file_exists($file)) $errors[] = "Missing model: " . $m;
}
$controllers = ["InvoiceController","WorkOrderController","EmployeeController","FleetController","ContractController","WalletController","ReportController","ZatcaController"];
foreach($controllers as $c) {
    $found = false;
    $dirs = ["/var/www/app/Http/Controllers/","/var/www/app/Http/Controllers/Api/V1/"];
    foreach($dirs as $dir) {
        if(file_exists($dir.$c.".php")) { $found = true; break; }
    }
    if(!$found) $errors[] = "Missing controller: " . $c;
}
if(empty($errors)) echo "All key files present\n";
else echo implode("\n", $errors) . "\n";
