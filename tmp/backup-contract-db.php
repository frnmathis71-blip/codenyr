<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() === 'sqlite') {
    $backup = storage_path('app/private/before-contract-catalog-'.date('Ymd-His').'.sqlite');
    $db->statement('VACUUM INTO ?', [$backup]);
    echo "Sauvegarde locale créée.\n";
}
