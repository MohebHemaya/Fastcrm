<?php

use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

function executeRaceRun(int $runIndex): array
{
    // Ensure all previous transactions are committed
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $uniquePrefix = 'RACE_'.uniqid().'_'.$runIndex;

    $order = WorkOrder::create([
        'code' => 'WO-'.substr(md5($uniquePrefix), 0, 8),
        'from_location' => 'القاهرة',
        'to_location' => 'الإسكندرية',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 10,
        'backup_needed' => 5,
        'status' => WorkOrderStatus::FILLING,
        'confirmed_count' => 0,
        'backup_count' => 0,
    ]);

    $driverIds = [];
    for ($i = 1; $i <= 30; $i++) {
        $driver = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($uniquePrefix.'_'.$i)) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Race Driver {$runIndex}_{$i}",
        ]);
        $driverIds[] = $driver->id;

        WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => WorkOrderRecipientStatus::SENT,
        ]);
    }

    // Commit all created records so child processes can see them
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $barrierDir = storage_path('framework/testing');
    if (! is_dir($barrierDir)) {
        mkdir($barrierDir, 0777, true);
    }
    $barrierFile = $barrierDir.DIRECTORY_SEPARATOR.'barrier_'.uniqid().'.lock';
    if (file_exists($barrierFile)) {
        @unlink($barrierFile);
    }

    $phpBinary = PHP_BINARY;
    $artisanPath = base_path('artisan');

    /** @var list<Process> $processes */
    $processes = [];
    foreach ($driverIds as $dId) {
        $p = new Process([
            $phpBinary,
            $artisanPath,
            'work-order:test-accept',
            (string) $order->id,
            (string) $dId,
            'accept',
            "--barrier={$barrierFile}",
            '--env=testing',
        ]);
        $p->setTimeout(30);
        $p->start();
        $processes[] = $p;
    }

    // Sleep 250ms to allow all 30 PHP processes to spin up and block on the barrier file
    usleep(250000);

    // Release the barrier: all 30 processes break simultaneously into AcceptOrderOffer::handle()
    touch($barrierFile);

    // Wait for all processes to complete
    foreach ($processes as $p) {
        $p->wait();
    }

    @unlink($barrierFile);

    // Assert that every process exited cleanly
    foreach ($processes as $idx => $p) {
        if ($p->getExitCode() !== 0) {
            throw new RuntimeException("Child process {$idx} failed with exit code {$p->getExitCode()}: ".$p->getErrorOutput().' | '.$p->getOutput());
        }
    }

    // Refresh order from DB
    $freshOrder = WorkOrder::find($order->id);

    $confirmed = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::CONFIRMED)
        ->get();
    $backup = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::BACKUP)
        ->get();
    $tooLate = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::TOO_LATE)
        ->get();

    $confirmedSeq = $confirmed->pluck('sequence_number')->sort()->values()->all();
    $backupSeq = $backup->pluck('sequence_number')->sort()->values()->all();

    // Cleanup DB records
    WorkOrderRecipient::where('work_order_id', $order->id)->delete();
    $order->delete();
    Driver::whereIn('id', $driverIds)->delete();

    // Start a transaction if needed by framework teardown
    if (DB::transactionLevel() === 0) {
        DB::beginTransaction();
    }

    return [
        'run' => $runIndex,
        'order_status' => $freshOrder->status->value,
        'confirmed_count' => $freshOrder->confirmed_count,
        'backup_count' => $freshOrder->backup_count,
        'confirmed_recipients' => $confirmed->count(),
        'backup_recipients' => $backup->count(),
        'too_late_recipients' => $tooLate->count(),
        'confirmed_sequence' => $confirmedSeq,
        'backup_sequence' => $backupSeq,
    ];
}

test('30 concurrent acceptances on a 10+5 order produces exact capacity and gapless sequence numbers (20 consecutive runs)', function () {
    $results = [];

    for ($run = 1; $run <= 20; $run++) {
        $res = executeRaceRun($run);
        $results[] = $res;

        expect($res['order_status'])->toBe('filled')
            ->and($res['confirmed_count'])->toBe(10)
            ->and($res['backup_count'])->toBe(5)
            ->and($res['confirmed_recipients'])->toBe(10)
            ->and($res['backup_recipients'])->toBe(5)
            ->and($res['too_late_recipients'])->toBe(15)
            ->and($res['confirmed_sequence'])->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
            ->and($res['backup_sequence'])->toBe([1, 2, 3, 4, 5]);
    }

    expect(count($results))->toBe(20);
});

test('same driver accepting twice concurrently is idempotent and does not double count', function () {
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $order = WorkOrder::create([
        'code' => 'WO-'.substr(md5(uniqid()), 0, 8),
        'from_location' => 'القاهرة',
        'to_location' => 'الجيزة',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 1,
        'backup_needed' => 0,
        'status' => WorkOrderStatus::FILLING,
        'confirmed_count' => 0,
        'backup_count' => 0,
    ]);

    $driver = Driver::create([
        'phone' => '+2010'.str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'status' => DriverStatus::ACTIVE,
        'name' => 'Single Driver',
    ]);

    $recipient = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $barrierFile = storage_path('framework/testing/barrier_'.uniqid().'.lock');

    $phpBinary = PHP_BINARY;
    $artisanPath = base_path('artisan');

    $p1 = new Process([
        $phpBinary, $artisanPath, 'work-order:test-accept',
        (string) $order->id, (string) $driver->id, 'accept',
        "--barrier={$barrierFile}", '--env=testing',
    ]);
    $p2 = new Process([
        $phpBinary, $artisanPath, 'work-order:test-accept',
        (string) $order->id, (string) $driver->id, 'accept',
        "--barrier={$barrierFile}", '--env=testing',
    ]);

    $p1->start();
    $p2->start();

    usleep(200000);
    touch($barrierFile);

    $p1->wait();
    $p2->wait();

    @unlink($barrierFile);

    $freshOrder = WorkOrder::find($order->id);
    $freshRecipient = WorkOrderRecipient::find($recipient->id);

    // Cleanup
    $freshRecipient->delete();
    $freshOrder->delete();
    $driver->delete();

    if (DB::transactionLevel() === 0) {
        DB::beginTransaction();
    }

    expect($freshOrder->confirmed_count)->toBe(1)
        ->and($freshOrder->status)->toBe(WorkOrderStatus::FILLED)
        ->and($freshRecipient->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($freshRecipient->sequence_number)->toBe(1);
});

test('acceptances racing concurrently with order cancellation are safely handled', function () {
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $order = WorkOrder::create([
        'code' => 'WO-'.substr(md5(uniqid()), 0, 8),
        'from_location' => 'القاهرة',
        'to_location' => 'طنطا',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 2,
        'backup_needed' => 1,
        'status' => WorkOrderStatus::FILLING,
        'confirmed_count' => 0,
        'backup_count' => 0,
    ]);

    $driverIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $driver = Driver::create([
            'phone' => '+2012'.str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Cancel Race Driver {$i}",
        ]);
        $driverIds[] = $driver->id;

        WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => WorkOrderRecipientStatus::SENT,
        ]);
    }

    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $barrierFile = storage_path('framework/testing/barrier_'.uniqid().'.lock');

    $phpBinary = PHP_BINARY;
    $artisanPath = base_path('artisan');

    // Spawn 4 acceptances and 1 cancellation
    $processes = [];
    for ($i = 0; $i < 4; $i++) {
        $p = new Process([
            $phpBinary, $artisanPath, 'work-order:test-accept',
            (string) $order->id, (string) $driverIds[$i], 'accept',
            "--barrier={$barrierFile}", '--env=testing',
        ]);
        $p->start();
        $processes[] = $p;
    }

    $cancelProcess = new Process([
        $phpBinary, $artisanPath, 'work-order:test-cancel',
        (string) $order->id,
        "--barrier={$barrierFile}", '--env=testing',
    ]);
    $cancelProcess->start();
    $processes[] = $cancelProcess;

    usleep(200000);
    touch($barrierFile);

    foreach ($processes as $p) {
        $p->wait();
    }

    @unlink($barrierFile);

    $freshOrder = WorkOrder::find($order->id);
    $activeRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
        ->whereIn('status', [WorkOrderRecipientStatus::CONFIRMED, WorkOrderRecipientStatus::BACKUP])
        ->count();

    // Cleanup
    WorkOrderRecipient::where('work_order_id', $order->id)->delete();
    $freshOrder->delete();
    Driver::whereIn('id', $driverIds)->delete();

    if (DB::transactionLevel() === 0) {
        DB::beginTransaction();
    }

    // Since cancel was executed, the order must end up cancelled and no recipient can remain confirmed/backup
    expect($freshOrder->status)->toBe(WorkOrderStatus::CANCELLED)
        ->and($activeRecipients)->toBe(0);
});
