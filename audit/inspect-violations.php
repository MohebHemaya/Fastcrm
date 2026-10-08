<?php
require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

config(['database.connections.mysql.database' => 'driver_system']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "=== INSPECTING ORDER #1 ===\n";
$order1 = DB::table('work_orders')->where('id', 1)->first();
print_r($order1);

echo "=== RECIPIENTS OF ORDER #1 ===\n";
$recipients1 = DB::table('work_order_recipients')->where('work_order_id', 1)->get();
foreach ($recipients1 as $r) {
    echo "ID: {$r->id}, Driver: {$r->driver_id}, Status: {$r->status}, Seq: {$r->sequence_number}, Sent: {$r->sent_at}, Resp: {$r->responded_at}\n";
}

echo "\n=== INSPECTING ACTIVE RECIPIENTS ON CANCELLED ORDERS (11089, 21292, 21293) ===\n";
$cancelledRecipients = DB::table('work_order_recipients')
    ->join('work_orders', 'work_orders.id', '=', 'work_order_recipients.work_order_id')
    ->whereIn('work_order_recipients.id', [11089, 21292, 21293])
    ->select('work_order_recipients.*', 'work_orders.code as order_code', 'work_orders.status as order_status')
    ->get();

foreach ($cancelledRecipients as $cr) {
    echo "Recipient ID: {$cr->id}, Order #{$cr->work_order_id} ({$cr->order_code}, status: {$cr->order_status}), Driver: {$cr->driver_id}, Recipient Status: {$cr->status}, Seq: {$cr->sequence_number}\n";
}
