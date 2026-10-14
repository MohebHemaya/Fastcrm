<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\WhatsAppMessage;
use App\Models\WhatsappGroup;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportService
{
    /**
     * Get catalogue of operational reports.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableReports(): array
    {
        return [
            [
                'id' => 'drivers',
                'title' => 'تقرير السائقين الشامل',
                'description' => 'استعراض وتصدير بيانات السائقين الكاملة وحالات التأهيل والمجموعات ومعدلات الإنجاز.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'UsersIcon',
            ],
            [
                'id' => 'work_orders',
                'title' => 'تقرير أوامر الشغل والرحلات',
                'description' => 'تفاصيل الرحلات، أرقام السائقين المؤكدين والاحتياط، التكلفة، ونسب التغطية التشغيلية.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'ClipboardDocumentListIcon',
            ],
            [
                'id' => 'whatsapp_messages',
                'title' => 'سجل محادثات ورسائل الواتساب',
                'description' => 'سجل الرسائل الصادرة والواردة، النصوص، حالات التسليم (وصلت، قُرئت، فاشلة)، والوقت.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'ChatBubbleLeftRightIcon',
            ],
            [
                'id' => 'questionnaire_answers',
                'title' => 'تقرير إجابات الاستبيان التفصيلي',
                'description' => 'سجل ردود السائقين على كل سؤال في الاستبيان، القيم، الأسئلة المتخطاة، وتواريخ الإجابة.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'DocumentTextIcon',
            ],
            [
                'id' => 'group_performance',
                'title' => 'تقرير أداء مجموعات الواتساب',
                'description' => 'مقارنة أداء مجموعات الاستقطاب، أعداد المنضمين، ونسب التحويل إلى سائقين معتمدين.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'ChartBarIcon',
            ],
            [
                'id' => 'needs_review',
                'title' => 'تقرير السائقين المطلوب مراجعتهم',
                'description' => 'حصر السائقين الذين تعثروا في الاستبيان أو لديهم علامات مراجعة تتطلب تدخلاً يدوياً.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'ExclamationTriangleIcon',
            ],
            [
                'id' => 'dispatched_offers',
                'title' => 'سجل عروض الرحلات واستجابة السائقين',
                'description' => 'متابعة توزيع أوامر الشغل، ردود السائقين (قبول، اعتذار، تأخر)، وتوقيتات الرد.',
                'badge' => 'بيانات حية + Excel',
                'icon' => 'PaperAirplaneIcon',
            ],
        ];
    }

    /**
     * Get paginated tabular data, column definitions, and real KPIs for a report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReportData(string $type, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return match ($type) {
            'drivers' => $this->getDriversData($filters, $page, $perPage),
            'work_orders' => $this->getWorkOrdersData($filters, $page, $perPage),
            'whatsapp_messages' => $this->getWhatsAppMessagesData($filters, $page, $perPage),
            'questionnaire_answers' => $this->getQuestionnaireAnswersData($filters, $page, $perPage),
            'group_performance' => $this->getGroupPerformanceData($filters, $page, $perPage),
            'needs_review' => $this->getNeedsReviewData($filters, $page, $perPage),
            'dispatched_offers' => $this->getDispatchedOffersData($filters, $page, $perPage),
            default => throw new \InvalidArgumentException("نوع التقرير غير مدعوم: {$type}"),
        };
    }

    /**
     * Stream an Excel (.xlsx) file download directly to the client.
     *
     * @param  array<string, mixed>  $filters
     */
    public function streamReportDownload(string $type, array $filters = []): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'report_export_') . '.xlsx';
        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile($tempPath);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('التقرير');

        $sheetView = new SheetView();
        $sheetView->setRightToLeft(true);
        $sheet->setSheetView($sheetView);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setFontSize(11)
            ->setBackgroundColor(Color::toARGB('1F2937'))
            ->setCellAlignment(CellAlignment::CENTER);

        // Fetch all rows for export (up to 10,000 for safety)
        $dataResult = $this->getReportData($type, $filters, 1, 10000);
        $columns = $dataResult['columns'];
        $rows = $dataResult['rows'];

        // Write header
        $headerLabels = array_map(fn ($col) => $col['label'], $columns);
        $writer->addRow(Row::fromValues($headerLabels, $headerStyle));

        // Write data rows
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($columns as $col) {
                $val = $row[$col['key']] ?? '—';
                if (is_bool($val)) {
                    $val = $val ? 'نعم' : 'لا';
                }
                $rowValues[] = (string) $val;
            }
            $writer->addRow(Row::fromValues($rowValues));
        }

        $writer->close();

        $timestamp = now()->format('Ymd_His');
        $filename = "report_{$type}_{$timestamp}.xlsx";

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /* --------------------------------------------------------------------------
     * 1. DRIVERS REPORT
     * -------------------------------------------------------------------------- */
    protected function getDriversData(array $filters, int $page, int $perPage): array
    {
        $query = Driver::query()->with(['whatsappGroups', 'metrics']);

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('phone', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['group_id'])) {
            $groupId = (int) $filters['group_id'];
            $query->whereHas('whatsappGroups', fn ($q) => $q->where('whatsapp_groups.id', $groupId));
        }
        $this->applyDateFilter($query, $filters);

        // Summary counts
        $total = (clone $query)->count();
        $activeCount = (clone $query)->where('status', DriverStatus::ACTIVE->value)->count();
        $inProgressCount = (clone $query)->where('status', DriverStatus::QUESTIONNAIRE_IN_PROGRESS->value)->count();
        $needsReviewCount = (clone $query)->where('needs_review', true)->count();

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'name', 'label' => 'اسم السائق', 'align' => 'start'],
            ['key' => 'phone', 'label' => 'رقم الهاتف', 'align' => 'start'],
            ['key' => 'status_label', 'label' => 'الحالة', 'align' => 'center'],
            ['key' => 'groups', 'label' => 'مجموعات الواتساب', 'align' => 'start'],
            ['key' => 'trips_completed', 'label' => 'رحلات مكتملة', 'align' => 'center'],
            ['key' => 'needs_review_badge', 'label' => 'مراجعة', 'align' => 'center'],
            ['key' => 'created_at', 'label' => 'تاريخ التسجيل', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $d) {
            $rows[] = [
                'id' => $d->id,
                'name' => $d->name ?: '—',
                'phone' => $d->phone,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'groups' => $d->whatsappGroups->pluck('name')->implode('، ') ?: '—',
                'trips_completed' => $d->metrics?->trips_completed ?? 0,
                'needs_review' => (bool) $d->needs_review,
                'needs_review_badge' => $d->needs_review ? '⚠️ بحاجة لمراجعة' : 'سليم',
                'created_at' => $d->created_at?->format('Y-m-d H:i') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'active' => $activeCount,
                'in_progress' => $inProgressCount,
                'needs_review' => $needsReviewCount,
                'activation_rate' => $total > 0 ? round(($activeCount / $total) * 100, 1) . '%' : '0%',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 2. WORK ORDERS REPORT
     * -------------------------------------------------------------------------- */
    protected function getWorkOrdersData(array $filters, int $page, int $perPage): array
    {
        $query = WorkOrder::query();

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('cargo_type', 'like', "%{$s}%")
                ->orWhere('from_location', 'like', "%{$s}%")
                ->orWhere('to_location', 'like', "%{$s}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilter($query, $filters);

        $totalOrders = (clone $query)->count();
        $tripsNeeded = (int) (clone $query)->sum('trips_needed');
        $confirmedCount = (int) (clone $query)->sum('confirmed_count');
        $fulfillmentRate = $tripsNeeded > 0 ? round(($confirmedCount / $tripsNeeded) * 100, 1) . '%' : '0%';

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'code', 'label' => 'كود الطلب', 'align' => 'center'],
            ['key' => 'route', 'label' => 'خط السير', 'align' => 'start'],
            ['key' => 'cargo_type', 'label' => 'نوع الحمولة', 'align' => 'start'],
            ['key' => 'pay_amount', 'label' => 'الأجر (ج.م)', 'align' => 'center'],
            ['key' => 'trips_needed', 'label' => 'المطلوب', 'align' => 'center'],
            ['key' => 'confirmed_count', 'label' => 'المؤكد', 'align' => 'center'],
            ['key' => 'coverage', 'label' => 'نسبة التغطية', 'align' => 'center'],
            ['key' => 'status_label', 'label' => 'الحالة', 'align' => 'center'],
            ['key' => 'scheduled_at', 'label' => 'موعد الرحلة', 'align' => 'center'],
            ['key' => 'created_at', 'label' => 'تاريخ الإنشاء', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $o) {
            $percent = $o->trips_needed > 0 ? round(($o->confirmed_count / $o->trips_needed) * 100) : 0;
            $rows[] = [
                'id' => $o->id,
                'code' => $o->code,
                'route' => "{$o->from_location} ➔ {$o->to_location}",
                'cargo_type' => $o->cargo_type ?: 'عامة',
                'pay_amount' => number_format((float) $o->pay_amount, 2),
                'trips_needed' => $o->trips_needed,
                'confirmed_count' => $o->confirmed_count,
                'coverage' => "{$percent}%",
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'scheduled_at' => $o->scheduled_at?->format('Y-m-d H:i') ?? '—',
                'created_at' => $o->created_at?->format('Y-m-d H:i') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total_orders' => $totalOrders,
                'trips_needed' => $tripsNeeded,
                'confirmed_count' => $confirmedCount,
                'fulfillment_rate' => $fulfillmentRate,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 3. WHATSAPP MESSAGES LOG REPORT
     * -------------------------------------------------------------------------- */
    protected function getWhatsAppMessagesData(array $filters, int $page, int $perPage): array
    {
        $query = WhatsAppMessage::query()->with('driver');

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('body', 'like', "%{$s}%")
                ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilter($query, $filters);

        $total = (clone $query)->count();
        $inbound = (clone $query)->where('direction', 'in')->count();
        $outbound = (clone $query)->where('direction', 'out')->count();
        $delivered = (clone $query)->where('direction', 'out')->whereIn('status', ['delivered', 'read'])->count();
        $read = (clone $query)->where('direction', 'out')->where('status', 'read')->count();

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'driver_name', 'label' => 'اسم السائق', 'align' => 'start'],
            ['key' => 'phone', 'label' => 'رقم الهاتف', 'align' => 'start'],
            ['key' => 'direction_label', 'label' => 'الاتجاه', 'align' => 'center'],
            ['key' => 'body', 'label' => 'نص الرسالة', 'align' => 'start'],
            ['key' => 'status_label', 'label' => 'الحالة', 'align' => 'center'],
            ['key' => 'created_at', 'label' => 'الوقت والتاريخ', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $m) {
            $dirLabel = $m->direction === WhatsAppMessageDirection::IN ? 'وارد من السائق 📥' : 'صادر للنظام 📤';
            $rows[] = [
                'id' => $m->id,
                'driver_name' => $m->driver?->name ?: '—',
                'phone' => $m->driver?->phone ?: '—',
                'direction' => $m->direction->value,
                'direction_label' => $dirLabel,
                'body' => $m->body ?: '—',
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'created_at' => $m->created_at?->format('Y-m-d H:i:s') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'delivered' => $delivered,
                'read' => $read,
                'delivery_rate' => $outbound > 0 ? round(($delivered / $outbound) * 100, 1) . '%' : '0%',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 4. QUESTIONNAIRE ANSWERS REPORT
     * -------------------------------------------------------------------------- */
    protected function getQuestionnaireAnswersData(array $filters, int $page, int $perPage): array
    {
        $query = DriverAnswer::query()
            ->with(['driver', 'question.currentVersion', 'option'])
            ->where('is_current', true);

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('value_text', 'like', "%{$s}%")
                ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        if (! empty($filters['question_id'])) {
            $query->where('question_id', (int) $filters['question_id']);
        }
        if (isset($filters['is_skipped']) && $filters['is_skipped'] !== '') {
            $query->where('is_skipped', (bool) $filters['is_skipped']);
        }
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('answered_at', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay(),
            ]);
        }

        $total = (clone $query)->count();
        $answered = (clone $query)->where('is_skipped', false)->count();
        $skipped = (clone $query)->where('is_skipped', true)->count();

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'driver_name', 'label' => 'اسم السائق', 'align' => 'start'],
            ['key' => 'phone', 'label' => 'رقم الهاتف', 'align' => 'start'],
            ['key' => 'question_text', 'label' => 'السؤال', 'align' => 'start'],
            ['key' => 'answer_text', 'label' => 'الإجابة', 'align' => 'start'],
            ['key' => 'skipped_label', 'label' => 'حالة الإجابة', 'align' => 'center'],
            ['key' => 'answered_at', 'label' => 'تاريخ الإجابة', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $ans) {
            $rows[] = [
                'id' => $ans->id,
                'driver_name' => $ans->driver?->name ?: '—',
                'phone' => $ans->driver?->phone ?: '—',
                'question_text' => $ans->question?->currentVersion?->text_ar ?? $ans->question?->key ?? '—',
                'answer_text' => $ans->display_value ?: '—',
                'skipped_label' => $ans->is_skipped ? 'تم التخطي' : 'مكتملة ✓',
                'answered_at' => $ans->answered_at?->format('Y-m-d H:i') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'answered' => $answered,
                'skipped' => $skipped,
                'completion_rate' => $total > 0 ? round(($answered / $total) * 100, 1) . '%' : '0%',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 5. WHATSAPP GROUPS PERFORMANCE REPORT
     * -------------------------------------------------------------------------- */
    protected function getGroupPerformanceData(array $filters, int $page, int $perPage): array
    {
        $query = WhatsappGroup::query();

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where('name', 'like', "%{$s}%");
        }

        $allGroups = $query->with('drivers')->get();

        $rows = [];
        $totalSourced = 0;
        $totalActive = 0;

        foreach ($allGroups as $grp) {
            $drivers = $grp->drivers;
            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $start = Carbon::parse($filters['start_date'])->startOfDay();
                $end = Carbon::parse($filters['end_date'])->endOfDay();
                $drivers = $drivers->filter(fn ($d) => $d->created_at && $d->created_at->between($start, $end));
            }

            $countTotal = $drivers->count();
            $countInvited = $drivers->where('status', DriverStatus::INVITED)->count();
            $countInterested = $drivers->where('status', DriverStatus::INTERESTED)->count();
            $countActive = $drivers->where('status', DriverStatus::ACTIVE)->count();

            $totalSourced += $countTotal;
            $totalActive += $countActive;

            $convRate = $countTotal > 0 ? round(($countActive / $countTotal) * 100, 1) : 0.0;

            $rows[] = [
                'id' => $grp->id,
                'name' => $grp->name,
                'total_drivers' => $countTotal,
                'invited_count' => $countInvited,
                'interested_count' => $countInterested,
                'active_count' => $countActive,
                'conversion_rate' => "{$convRate}%",
                'raw_conv' => $convRate,
            ];
        }

        // Sort by total drivers desc
        usort($rows, fn ($a, $b) => $b['total_drivers'] <=> $a['total_drivers']);

        $totalGroups = count($rows);
        $offset = ($page - 1) * $perPage;
        $slicedRows = array_slice($rows, $offset, $perPage);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'name', 'label' => 'اسم مجموعة الواتساب', 'align' => 'start'],
            ['key' => 'total_drivers', 'label' => 'إجمالي المسجلين', 'align' => 'center'],
            ['key' => 'invited_count', 'label' => 'تمت دعوتهم', 'align' => 'center'],
            ['key' => 'interested_count', 'label' => 'أبدوا الاهتمام', 'align' => 'center'],
            ['key' => 'active_count', 'label' => 'سائقون نشطون (شغالين)', 'align' => 'center'],
            ['key' => 'conversion_rate', 'label' => 'معدل التحويل', 'align' => 'center'],
        ];

        return [
            'columns' => $columns,
            'rows' => $slicedRows,
            'summary' => [
                'total_groups' => $totalGroups,
                'total_drivers' => $totalSourced,
                'total_active' => $totalActive,
                'avg_conversion_rate' => $totalSourced > 0 ? round(($totalActive / $totalSourced) * 100, 1) . '%' : '0%',
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalGroups,
                'last_page' => (int) ceil($totalGroups / max($perPage, 1)),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 6. DRIVERS REQUIRING REVIEW REPORT
     * -------------------------------------------------------------------------- */
    protected function getNeedsReviewData(array $filters, int $page, int $perPage): array
    {
        $query = Driver::query()->where('needs_review', true)->with(['whatsappGroups']);

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('phone', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('review_reason', 'like', "%{$s}%"));
        }
        $this->applyDateFilter($query, $filters);

        $total = (clone $query)->count();
        $withRetries = (clone $query)->where('questionnaire_retries', '>', 0)->count();

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'name', 'label' => 'اسم السائق', 'align' => 'start'],
            ['key' => 'phone', 'label' => 'رقم الهاتف', 'align' => 'start'],
            ['key' => 'status_label', 'label' => 'الحالة الحالية', 'align' => 'center'],
            ['key' => 'review_reason', 'label' => 'سبب المراجعة', 'align' => 'start'],
            ['key' => 'retries', 'label' => 'محاولات الاستبيان', 'align' => 'center'],
            ['key' => 'created_at', 'label' => 'تاريخ التسجيل', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $d) {
            $rows[] = [
                'id' => $d->id,
                'name' => $d->name ?: '—',
                'phone' => $d->phone,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'review_reason' => $d->review_reason ?: 'تجاوز الحد الأقصى للمحاولات أو إخفاق في المعايير',
                'retries' => $d->questionnaire_retries ?? 0,
                'created_at' => $d->created_at?->format('Y-m-d H:i') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'with_retries' => $withRetries,
                'manual_flags' => max(0, $total - $withRetries),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /* --------------------------------------------------------------------------
     * 7. DISPATCHED OFFERS REPORT
     * -------------------------------------------------------------------------- */
    protected function getDispatchedOffersData(array $filters, int $page, int $perPage): array
    {
        $query = WorkOrderRecipient::query()->with(['workOrder', 'driver']);

        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->whereHas('workOrder', fn ($wq) => $wq->where('code', 'like', "%{$s}%"))
                ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $this->applyDateFilter($query, $filters);

        $total = (clone $query)->count();
        $confirmed = (clone $query)->where('status', WorkOrderRecipientStatus::CONFIRMED->value)->count();
        $declined = (clone $query)->where('status', WorkOrderRecipientStatus::DECLINED->value)->count();
        $sent = (clone $query)->where('status', WorkOrderRecipientStatus::SENT->value)->count();

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $columns = [
            ['key' => 'id', 'label' => 'المعرف', 'align' => 'center'],
            ['key' => 'work_order_code', 'label' => 'كود أمر الشغل', 'align' => 'center'],
            ['key' => 'driver_name', 'label' => 'اسم السائق', 'align' => 'start'],
            ['key' => 'phone', 'label' => 'رقم الهاتف', 'align' => 'start'],
            ['key' => 'status_label', 'label' => 'حالة العرض', 'align' => 'center'],
            ['key' => 'sent_at', 'label' => 'تاريخ الإرسال', 'align' => 'center'],
            ['key' => 'responded_at', 'label' => 'تاريخ الرد', 'align' => 'center'],
        ];

        $rows = [];
        foreach ($paginator->items() as $r) {
            $rows[] = [
                'id' => $r->id,
                'work_order_code' => $r->workOrder?->code ?: '—',
                'driver_name' => $r->driver?->name ?: '—',
                'phone' => $r->driver?->phone ?: '—',
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'sent_at' => $r->sent_at?->format('Y-m-d H:i') ?? '—',
                'responded_at' => $r->responded_at?->format('Y-m-d H:i') ?? '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'confirmed' => $confirmed,
                'declined' => $declined,
                'pending' => $sent,
                'acceptance_rate' => $total > 0 ? round(($confirmed / $total) * 100, 1) . '%' : '0%',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Helper to apply start_date / end_date to query.
     */
    protected function applyDateFilter($query, array $filters, string $column = 'created_at'): void
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween($column, [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay(),
            ]);
        }
    }

    /* --------------------------------------------------------------------------
     * Existing Overview / Funnel / WhatsApp / Fulfillment methods (for backward compatibility)
     * -------------------------------------------------------------------------- */
    public function getOverviewReport(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        $driverQ = Driver::query();
        if ($start && $end) {
            $driverQ->whereBetween('created_at', [$start, $end]);
        }

        $totalDrivers = (clone $driverQ)->count();
        $activeDrivers = (clone $driverQ)->where('status', DriverStatus::ACTIVE->value)->count();
        $inProgressDrivers = (clone $driverQ)->where('status', DriverStatus::QUESTIONNAIRE_IN_PROGRESS->value)->count();
        $needsReviewCount = (clone $driverQ)->where('needs_review', true)->count();

        $msgQ = WhatsAppMessage::query();
        if ($start && $end) {
            $msgQ->whereBetween('created_at', [$start, $end]);
        }

        $totalMessages = (clone $msgQ)->count();
        $inboundMessages = (clone $msgQ)->where('direction', 'in')->count();
        $outboundMessages = (clone $msgQ)->where('direction', 'out')->count();
        $deliveredOutbound = (clone $msgQ)->where('direction', 'out')->whereIn('status', ['delivered', 'read'])->count();
        $readOutbound = (clone $msgQ)->where('direction', 'out')->where('status', 'read')->count();

        $deliveryRate = $outboundMessages > 0 ? round(($deliveredOutbound / $outboundMessages) * 100, 1) : 0.0;
        $readRate = $outboundMessages > 0 ? round(($readOutbound / $outboundMessages) * 100, 1) : 0.0;

        $orderQ = WorkOrder::query();
        if ($start && $end) {
            $orderQ->whereBetween('created_at', [$start, $end]);
        }

        $totalOrders = (clone $orderQ)->count();
        $completedOrders = (clone $orderQ)->where('status', WorkOrderStatus::COMPLETED->value)->count();
        $totalRequiredDrivers = (int) (clone $orderQ)->sum('trips_needed');
        $totalConfirmedDrivers = (int) (clone $orderQ)->sum('confirmed_count');
        $fulfillmentRate = $totalRequiredDrivers > 0 ? round(($totalConfirmedDrivers / $totalRequiredDrivers) * 100, 1) : 0.0;

        return [
            'drivers' => [
                'total' => $totalDrivers,
                'active' => $activeDrivers,
                'in_progress' => $inProgressDrivers,
                'needs_review' => $needsReviewCount,
                'activation_rate' => $totalDrivers > 0 ? round(($activeDrivers / $totalDrivers) * 100, 1) : 0.0,
            ],
            'whatsapp' => [
                'total' => $totalMessages,
                'inbound' => $inboundMessages,
                'outbound' => $outboundMessages,
                'delivered' => $deliveredOutbound,
                'read' => $readOutbound,
                'delivery_rate' => $deliveryRate,
                'read_rate' => $readRate,
            ],
            'work_orders' => [
                'total' => $totalOrders,
                'completed' => $completedOrders,
                'required_drivers' => $totalRequiredDrivers,
                'confirmed_drivers' => $totalConfirmedDrivers,
                'fulfillment_rate' => $fulfillmentRate,
            ],
        ];
    }

    public function getFunnelReport(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        $driverQ = Driver::query();
        if ($start && $end) {
            $driverQ->whereBetween('created_at', [$start, $end]);
        }

        $countsByStatus = (clone $driverQ)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $total = (clone $driverQ)->count();
        $invited = (int) ($countsByStatus[DriverStatus::INVITED->value] ?? 0);
        $interested = (int) ($countsByStatus[DriverStatus::INTERESTED->value] ?? 0);
        $inProgress = (int) ($countsByStatus[DriverStatus::QUESTIONNAIRE_IN_PROGRESS->value] ?? 0);
        $active = (int) ($countsByStatus[DriverStatus::ACTIVE->value] ?? 0);
        $declined = (int) ($countsByStatus[DriverStatus::DECLINED->value] ?? 0);
        $optedOut = (int) ($countsByStatus[DriverStatus::OPTED_OUT->value] ?? 0);

        $steps = [
            ['step' => 'registered', 'label' => 'مسجلون في النظام', 'count' => $total, 'percentage' => 100],
            ['step' => 'invited', 'label' => 'تمت دعوتهم بالواتساب', 'count' => $invited + $interested + $inProgress + $active, 'percentage' => $total > 0 ? round((($invited + $interested + $inProgress + $active) / $total) * 100, 1) : 0],
            ['step' => 'interested', 'label' => 'أبدوا الاهتمام بالعمل', 'count' => $interested + $inProgress + $active, 'percentage' => $total > 0 ? round((($interested + $inProgress + $active) / $total) * 100, 1) : 0],
            ['step' => 'questionnaire', 'label' => 'بدأوا / استكملوا الاستبيان', 'count' => $inProgress + $active, 'percentage' => $total > 0 ? round((($inProgress + $active) / $total) * 100, 1) : 0],
            ['step' => 'active', 'label' => 'سائقون معتمدون (شغالين معانا)', 'count' => $active, 'percentage' => $total > 0 ? round(($active / $total) * 100, 1) : 0],
        ];

        return [
            'funnel_steps' => $steps,
            'status_breakdown' => [
                'total' => $total,
                'active' => $active,
                'in_progress' => $inProgress,
                'interested' => $interested,
                'declined' => $declined,
                'opted_out' => $optedOut,
            ],
        ];
    }

    public function getWhatsAppReport(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        $msgQ = WhatsAppMessage::query();
        if ($start && $end) {
            $msgQ->whereBetween('created_at', [$start, $end]);
        }

        $statusCounts = (clone $msgQ)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $directionCounts = (clone $msgQ)
            ->select('direction', DB::raw('COUNT(*) as count'))
            ->groupBy('direction')
            ->pluck('count', 'direction')
            ->all();

        $total = array_sum($statusCounts);
        $sent = (int) ($statusCounts['sent'] ?? 0);
        $delivered = (int) ($statusCounts['delivered'] ?? 0);
        $read = (int) ($statusCounts['read'] ?? 0);
        $failed = (int) ($statusCounts['failed'] ?? 0);
        $queued = (int) ($statusCounts['queued'] ?? 0);

        $inbound = (int) ($directionCounts['in'] ?? 0);
        $outbound = (int) ($directionCounts['out'] ?? 0);

        return [
            'metrics' => [
                'total' => $total,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'delivered' => $delivered,
                'read' => $read,
                'failed' => $failed,
                'queued' => $queued,
                'delivery_rate' => $outbound > 0 ? round((($delivered + $read) / $outbound) * 100, 1) : 0,
                'read_rate' => $outbound > 0 ? round(($read / $outbound) * 100, 1) : 0,
            ],
        ];
    }

    public function getFulfillmentReport(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        $orderQ = WorkOrder::query();
        if ($start && $end) {
            $orderQ->whereBetween('created_at', [$start, $end]);
        }

        $totalOrders = (clone $orderQ)->count();
        $requiredSum = (int) (clone $orderQ)->sum('trips_needed');
        $confirmedSum = (int) (clone $orderQ)->sum('confirmed_count');

        return [
            'total_orders' => $totalOrders,
            'required_drivers' => $requiredSum,
            'confirmed_drivers' => $confirmedSum,
            'fulfillment_rate' => $requiredSum > 0 ? round(($confirmedSum / $requiredSum) * 100, 1) : 0,
        ];
    }
}
