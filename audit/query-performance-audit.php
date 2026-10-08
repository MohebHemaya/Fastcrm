<?php
// AUDIT Stage 9: Performance Re-check & Slow Query Audit
// Target DB: driver_system_audit (seeded with 5,000+ drivers)

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\Question;
use App\Exports\StreamingDriversExport;
use App\Services\DriverMatcher;
use App\Services\DashboardService;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 9: QUERY PERFORMANCE & EXPLAIN PLAN AUDIT\n";
echo "Database: driver_system_audit (" . Driver::count() . " drivers)\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$queries = [];

// 1. Streaming Drivers Export Query
$export = new StreamingDriversExport(['status' => 'active']);
$exportSql = $export->query()->toSql();
$exportBindings = $export->query()->getBindings();
$queries['1. Streaming Drivers Export Query'] = [
    'sql' => $exportSql,
    'bindings' => $exportBindings,
];

// 2. Driver Matcher Query with Filters
$matcher = app(DriverMatcher::class);
$matchQuery = $matcher->buildQuery(
    filters: [],
    excludeSameDayConfirmed: true,
    scheduledAt: now()->addDay(),
    minAvgRating: 4.0,
    maxNoShows: 0
);
$queries['2. Driver Matcher with Same-Day & System Metrics Filters'] = [
    'sql' => $matchQuery->toSql(),
    'bindings' => $matchQuery->getBindings(),
];

// 3. Driver Phone Lookup Query (Inbound webhook hot path)
$driverLookup = Driver::where('phone', '+201101234567');
$queries['3. Driver Phone E.164 Lookup (Webhook Hot Path)'] = [
    'sql' => $driverLookup->toSql(),
    'bindings' => $driverLookup->getBindings(),
];

// 4. Open Offers Lookup (Inbound message response hot path)
$openOffers = DB::table('work_order_recipients')
    ->join('work_orders', 'work_orders.id', '=', 'work_order_recipients.work_order_id')
    ->where('work_order_recipients.driver_id', 1)
    ->where('work_order_recipients.status', 'sent')
    ->where('work_orders.status', 'filling');
$queries['4. Open Work Order Offers for Driver'] = [
    'sql' => $openOffers->toSql(),
    'bindings' => $openOffers->getBindings(),
];

// 5. Questionnaire Next Unanswered Question Query
$nextQuestion = Question::where('is_active', true)
    ->orderBy('sort_order', 'asc')
    ->whereNotIn('id', [1, 2]);
$queries['5. Next Unanswered Question Query'] = [
    'sql' => $nextQuestion->toSql(),
    'bindings' => $nextQuestion->getBindings(),
];

// Execute EXPLAIN and benchmark each
foreach ($queries as $label => $q) {
    echo "--------------------------------------------------------------------\n";
    echo "QUERY: {$label}\n";
    echo "SQL: {$q['sql']}\n\n";

    // EXPLAIN plan
    $explainSql = "EXPLAIN " . $q['sql'];
    try {
        $explainRows = DB::select($explainSql, $q['bindings']);
        printf("%-15s %-12s %-12s %-15s %-15s %-10s %-25s\n",
            'table', 'type', 'possible_keys', 'key', 'key_len', 'rows', 'Extra');
        printf("%s\n", str_repeat('-', 95));
        foreach ($explainRows as $row) {
            printf("%-15s %-12s %-12s %-15s %-15s %-10s %-25s\n",
                $row->table ?? '—',
                $row->type ?? '—',
                substr((string)($row->possible_keys ?? '—'), 0, 12),
                substr((string)($row->key ?? '—'), 0, 15),
                $row->key_len ?? '—',
                $row->rows ?? '—',
                substr((string)($row->Extra ?? '—'), 0, 25)
            );
        }

        // Measure execution time over 10 iterations
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $t0 = microtime(true);
            DB::select($q['sql'], $q['bindings']);
            $times[] = (microtime(true) - $t0) * 1000;
        }
        $avgMs = round(array_sum($times) / count($times), 2);
        echo "\nExecution Time (10 runs avg): {$avgMs} ms\n\n";
    } catch (\Throwable $e) {
        echo "EXPLAIN / Run Error: {$e->getMessage()}\n\n";
    }
}

// 6. Benchmark Dashboard Summary Service Execution
echo "--------------------------------------------------------------------\n";
echo "BENCHMARK: DashboardService::getSummary()\n";
$dashboard = app(DashboardService::class);
$dTimes = [];
for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    $dashboard->bustCache();
    $summary = $dashboard->getSummary();
    $dTimes[] = (microtime(true) - $t0) * 1000;
}
$avgDashMs = round(array_sum($dTimes) / count($dTimes), 2);
echo "DashboardService uncached avg latency: {$avgDashMs} ms across 5,000 drivers\n";
echo "--------------------------------------------------------------------\n\n";

echo "STAGE 9 PERFORMANCE AUDIT COMPLETED.\n";
