<?php
require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

config(['database.connections.mysql.database' => 'driver_system']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "=== ORDERS 6 and 8 EVENTS ===\n";
$events = DB::table('work_order_events')->whereIn('work_order_id', [6, 8])->get();
foreach ($events as $e) {
    echo "Order #{$e->work_order_id}: Action={$e->action}, Desc={$e->description}, Meta={$e->metadata}\n";
}

echo "\n=== RECIPIENTS OF ORDER 6 ===\n";
$r6 = DB::table('work_order_recipients')->where('work_order_id', 6)->get();
foreach ($r6 as $r) {
    echo "ID: {$r->id}, Driver: {$r->driver_id}, Status: {$r->status}, Seq: {$r->sequence_number}\n";
}

echo "\n=== RECIPIENTS OF ORDER 8 ===\n";
$r8 = DB::table('work_order_recipients')->where('work_order_id', 8)->get();
foreach ($r8 as $r) {
    echo "ID: {$r->id}, Driver: {$r->driver_id}, Status: {$r->status}, Seq: {$r->sequence_number}\n";
}
