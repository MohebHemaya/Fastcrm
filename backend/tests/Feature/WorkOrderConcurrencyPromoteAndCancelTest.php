<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('REAL Concurrency: 2 admins promoting different backups into ONE free slot across 20 consecutive runs', function () {
    $phpBinary = PHP_BINARY;
    $artisanPath = base_path('artisan');

    $barrierDir = storage_path('framework/testing');
    if (! is_dir($barrierDir)) {
        mkdir($barrierDir, 0777, true);
    }

    $resultsSummary = [];

    for ($run = 1; $run <= 20; $run++) {
        // Commit any pending parent transactions so child processes see clean data
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $prefix = "CONC_P_{$run}_".uniqid();

        // 1. Create order with 2 trips needed, 1 confirmed, 1 free slot, 2 backups
        $order = WorkOrder::create([
            'code' => 'WO-P-'.substr(md5($prefix), 0, 6),
            'from_location' => 'القاهرة',
            'to_location' => 'الأسكندرية',
            'scheduled_at' => now()->addDay(),
            'trips_needed' => 2,
            'backup_needed' => 2,
            'confirmed_count' => 1, // Exactly 1 free slot
            'backup_count' => 2,
            'status' => WorkOrderStatus::FILLED,
        ]);

        $driverConfirmed = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($prefix.'_c')) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Driver C {$run}",
        ]);

        $driverBackup1 = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($prefix.'_b1')) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Backup 1 {$run}",
        ]);

        $driverBackup2 = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($prefix.'_b2')) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Backup 2 {$run}",
        ]);

        WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driverConfirmed->id,
            'status' => WorkOrderRecipientStatus::CONFIRMED,
            'sequence_number' => 1,
        ]);

        $recB1 = WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driverBackup1->id,
            'status' => WorkOrderRecipientStatus::BACKUP,
            'sequence_number' => 1,
        ]);

        $recB2 = WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driverBackup2->id,
            'status' => WorkOrderRecipientStatus::BACKUP,
            'sequence_number' => 2,
        ]);

        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $barrierFile = $barrierDir.DIRECTORY_SEPARATOR."barrier_promote_{$run}_".uniqid().'.lock';
        if (file_exists($barrierFile)) {
            @unlink($barrierFile);
        }

        // Spawn 2 independent PHP processes targeting the 2 different backups
        $p1 = new Process([
            $phpBinary,
            $artisanPath,
            'work-order:test-followup',
            'promote',
            (string) $order->id,
            (string) $recB1->id,
            "--barrier={$barrierFile}",
            '--env=testing',
        ]);

        $p2 = new Process([
            $phpBinary,
            $artisanPath,
            'work-order:test-followup',
            'promote',
            (string) $order->id,
            (string) $recB2->id,
            "--barrier={$barrierFile}",
            '--env=testing',
        ]);

        $p1->setTimeout(15);
        $p2->setTimeout(15);

        $p1->start();
        $p2->start();

        // Allow both processes to spin up and wait at barrier
        usleep(100000);

        // Release barrier
        touch($barrierFile);

        $p1->wait();
        $p2->wait();

        @unlink($barrierFile);

        $out1 = json_decode(trim($p1->getOutput()), true) ?: [];
        $out2 = json_decode(trim($p2->getOutput()), true) ?: [];

        $p1Success = ! empty($out1['success']);
        $p2Success = ! empty($out2['success']);

        // Assert that EXACTLY one succeeded and one was rejected
        expect($p1Success xor $p2Success)->toBeTrue(
            "Run {$run}: Expected exactly one promote to succeed. P1: {$p1->getOutput()}, P2: {$p2->getOutput()}"
        );

        // Assert DB counts and sequences
        $order->refresh();
        expect($order->confirmed_count)->toBe(2)
            ->and($order->backup_count)->toBe(1);

        $confirmedRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::CONFIRMED)
            ->orderBy('sequence_number', 'asc')
            ->get();
        expect($confirmedRecipients)->toHaveCount(2);

        $confirmedSeqs = $confirmedRecipients->pluck('sequence_number')->all();
        expect($confirmedSeqs)->toBe([1, 2]);

        $remainingBackup = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::BACKUP)
            ->first();
        expect($remainingBackup)->not->toBeNull()
            ->and($remainingBackup->sequence_number)->toBe(1);

        $winner = $p1Success ? 'Backup 1' : 'Backup 2';
        $resultsSummary[] = "Run #{$run}: SUCCESS (Winner: {$winner}, Confirmed: 2/2, Backup: 1/2, Sequences: 1..2)";
    }

    echo "\n=== Concurrency Promote Benchmark Results (20 Runs) ===\n";
    foreach ($resultsSummary as $res) {
        echo "{$res}\n";
    }
    echo "========================================================\n";
});

test('REAL Concurrency: Cancel racing with a promote maintains full consistency', function () {
    $phpBinary = PHP_BINARY;
    $artisanPath = base_path('artisan');

    $barrierDir = storage_path('framework/testing');
    if (! is_dir($barrierDir)) {
        mkdir($barrierDir, 0777, true);
    }

    for ($run = 1; $run <= 5; $run++) {
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $prefix = "CONC_CP_{$run}_".uniqid();

        $order = WorkOrder::create([
            'code' => 'WO-CP-'.substr(md5($prefix), 0, 6),
            'from_location' => 'القاهرة',
            'to_location' => 'طنطا',
            'scheduled_at' => now()->addDay(),
            'trips_needed' => 2,
            'backup_needed' => 1,
            'confirmed_count' => 1,
            'backup_count' => 1,
            'status' => WorkOrderStatus::FILLED,
        ]);

        $dConfirmed = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($prefix.'_c')) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Driver C {$run}",
        ]);

        $dBackup = Driver::create([
            'phone' => '+2011'.str_pad((string) (abs(crc32($prefix.'_b')) % 100000000), 8, '0', STR_PAD_LEFT),
            'status' => DriverStatus::ACTIVE,
            'name' => "Driver B {$run}",
        ]);

        WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $dConfirmed->id,
            'status' => WorkOrderRecipientStatus::CONFIRMED,
            'sequence_number' => 1,
        ]);

        $recB = WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $dBackup->id,
            'status' => WorkOrderRecipientStatus::BACKUP,
            'sequence_number' => 1,
        ]);

        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $barrierFile = $barrierDir.DIRECTORY_SEPARATOR."barrier_cancel_promote_{$run}_".uniqid().'.lock';
        if (file_exists($barrierFile)) {
            @unlink($barrierFile);
        }

        // Process 1: Cancel
        $pCancel = new Process([
            $phpBinary,
            $artisanPath,
            'work-order:test-followup',
            'cancel',
            (string) $order->id,
            "--barrier={$barrierFile}",
            '--env=testing',
        ]);

        // Process 2: Promote
        $pPromote = new Process([
            $phpBinary,
            $artisanPath,
            'work-order:test-followup',
            'promote',
            (string) $order->id,
            (string) $recB->id,
            "--barrier={$barrierFile}",
            '--env=testing',
        ]);

        $pCancel->setTimeout(15);
        $pPromote->setTimeout(15);

        $pCancel->start();
        $pPromote->start();

        usleep(100000);
        touch($barrierFile);

        $pCancel->wait();
        $pPromote->wait();

        @unlink($barrierFile);

        // Verify order is CANCELLED (cancel always succeeds eventually)
        $order->refresh();
        expect($order->status)->toBe(WorkOrderStatus::CANCELLED);

        // Verify all recipients are in a valid state (no half-promoted dangling state)
        $recipients = WorkOrderRecipient::where('work_order_id', $order->id)->get();
        foreach ($recipients as $r) {
            expect($r->status)->toBe(WorkOrderRecipientStatus::CANCELLED);
        }
    }
});
