<?php

namespace App\Console\Commands;

use App\Exports\DriversExport;
use App\Exports\WorkOrderExport;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\WhatsappGroup;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\DriverProfilePdfService;
use App\Services\WorkOrderPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class GeneratePhase7SamplesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-phase7-samples';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Phase 7 sample files (Excel & PDF) in docs/samples/';

    /**
     * Execute the console command.
     */
    public function handle(
        WorkOrderPdfService $workOrderPdfService,
        DriverProfilePdfService $driverProfilePdfService
    ): int {
        $samplesDir = base_path('../docs/samples');
        if (! File::exists($samplesDir)) {
            File::makeDirectory($samplesDir, 0755, true);
        }

        $this->info('Generating Phase 7 sample files in docs/samples/...');

        // 1. Ensure sample driver exists
        $driver = Driver::with(['whatsappGroups', 'answers', 'metrics'])->first();
        if (! $driver) {
            $group = WhatsappGroup::firstOrCreate(['name' => 'جروب سائقي الجيزة']);
            $driver = Driver::create([
                'phone' => '+201012345678',
                'name' => 'أحمد محمود عبد الرحمن',
                'status' => 'active',
                'notes' => 'سائق ملتزم ولديه خبرة في خطوط الصعيد والوجه البحري.',
            ]);
            $driver->whatsappGroups()->sync([$group->id]);
            DriverMetric::create([
                'driver_id' => $driver->id,
                'trips_completed' => 24,
                'avg_rating' => 4.85,
                'no_shows' => 0,
                'cancellations' => 1,
                'late_count' => 2,
                'last_trip_at' => now()->subDays(2),
            ]);
        }

        // 2. Ensure sample work order exists
        $workOrder = WorkOrder::with(['recipients.driver'])->first();
        if (! $workOrder) {
            $workOrder = WorkOrder::create([
                'code' => 'WO-0001',
                'title' => 'توزيع بضائع ومستلزمات طبية',
                'from_location' => 'مستودع 6 أكتوبر الرئيسي',
                'to_location' => 'فروع الدلتا (طنطا - المنصورة)',
                'scheduled_at' => now()->addDays(2)->setTime(8, 0),
                'trips_needed' => 3,
                'backup_needed' => 2,
                'status' => 'filled',
                'confirmed_count' => 3,
                'backup_count' => 2,
                'notes' => 'يرجى الالتزام بمواعيد التحميل وإحضار إيصالات التسليم معتمدة ومختومة.',
                'created_by' => 1,
            ]);

            // Add confirmed recipients
            WorkOrderRecipient::create([
                'work_order_id' => $workOrder->id,
                'driver_id' => $driver->id,
                'status' => 'confirmed',
                'sequence_number' => 1,
                'sent_at' => now()->subHours(5),
                'responded_at' => now()->subHours(4),
            ]);

            $d2 = Driver::create([
                'phone' => '+201198765432',
                'name' => 'محمد علي إبراهيم',
                'status' => 'active',
            ]);
            WorkOrderRecipient::create([
                'work_order_id' => $workOrder->id,
                'driver_id' => $d2->id,
                'status' => 'confirmed',
                'sequence_number' => 2,
                'sent_at' => now()->subHours(5),
                'responded_at' => now()->subHours(3),
            ]);

            $d3 = Driver::create([
                'phone' => '+201234567890',
                'name' => 'خالد حسن الشريف',
                'status' => 'active',
            ]);
            WorkOrderRecipient::create([
                'work_order_id' => $workOrder->id,
                'driver_id' => $d3->id,
                'status' => 'confirmed',
                'sequence_number' => 3,
                'sent_at' => now()->subHours(5),
                'responded_at' => now()->subHours(2),
            ]);

            // Add backup recipients
            $d4 = Driver::create([
                'phone' => '+201501234567',
                'name' => 'ياسر كمال الصاوي',
                'status' => 'active',
            ]);
            WorkOrderRecipient::create([
                'work_order_id' => $workOrder->id,
                'driver_id' => $d4->id,
                'status' => 'backup',
                'sequence_number' => 1,
                'sent_at' => now()->subHours(5),
                'responded_at' => now()->subHour(),
            ]);
        }

        // Generate 1: Driver Excel
        $driverExcelPath = $samplesDir.'/driver_export_sample.xlsx';
        Excel::store(new DriversExport([]), 'temp_driver_sample.xlsx', 'local');
        $storedDriverExcel = storage_path('app/temp_driver_sample.xlsx');
        if (! File::exists($storedDriverExcel)) {
            $storedDriverExcel = storage_path('app/private/temp_driver_sample.xlsx');
        }
        File::copy($storedDriverExcel, $driverExcelPath);
        File::delete($storedDriverExcel);
        $this->info("✓ Generated: {$driverExcelPath}");

        // Generate 2: Order Excel
        $orderExcelPath = $samplesDir.'/order_report_sample.xlsx';
        Excel::store(new WorkOrderExport($workOrder), 'temp_order_sample.xlsx', 'local');
        $storedOrderExcel = storage_path('app/temp_order_sample.xlsx');
        if (! File::exists($storedOrderExcel)) {
            $storedOrderExcel = storage_path('app/private/temp_order_sample.xlsx');
        }
        File::copy($storedOrderExcel, $orderExcelPath);
        File::delete($storedOrderExcel);
        $this->info("✓ Generated: {$orderExcelPath}");

        // Generate 3: Order PDF
        $orderPdfPath = $samplesDir."/order_{$workOrder->code}_sample.pdf";
        $pdfBinary = $workOrderPdfService->generatePdf($workOrder);
        File::put($orderPdfPath, $pdfBinary);
        $this->info("✓ Generated: {$orderPdfPath}");

        // Generate 4: Driver Profile PDF
        $driverPdfPath = $samplesDir.'/driver_profile_sample.pdf';
        $driverPdfBinary = $driverProfilePdfService->generatePdf($driver);
        File::put($driverPdfPath, $driverPdfBinary);
        $this->info("✓ Generated: {$driverPdfPath}");

        $this->info('All sample files successfully generated in docs/samples/!');

        return 0;
    }
}
