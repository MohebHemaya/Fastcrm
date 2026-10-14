import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Button,
  Input,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Spinner,
  Pagination,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  UsersIcon,
  ClipboardDocumentListIcon,
  ChatBubbleLeftRightIcon,
  DocumentTextIcon,
  ChartBarIcon,
  ExclamationTriangleIcon,
  PaperAirplaneIcon,
  CalendarIcon,
  ArrowPathIcon,
  ArrowDownTrayIcon,
  MagnifyingGlassIcon,
} from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import { ar } from '@/i18n/ar';
import reportService, {
  type ReportTableData,
  type ReportQueryParams,
} from '@/services/reportService';
import { useUiStore } from '@/lib/stores/useUiStore';

const REPORT_TABS: Array<{
  id: string;
  title: string;
  shortTitle: string;
  description: string;
  icon: React.ComponentType<{ className?: string }>;
  color: string;
}> = [
  {
    id: 'drivers',
    title: 'تقرير السائقين الشامل',
    shortTitle: 'السائقون',
    description: 'استعراض وتصدير بيانات السائقين الكاملة، حالات التأهيل، المجموعات، ومعدلات الرحلات.',
    icon: UsersIcon,
    color: 'text-blue-600 bg-blue-50 dark:bg-blue-950/40 dark:text-blue-400',
  },
  {
    id: 'work_orders',
    title: 'تقرير أوامر الشغل والرحلات',
    shortTitle: 'أوامر الشغل',
    description: 'تفاصيل الرحلات التشغيلية، السائقين المؤكدين والاحتياطيين، الأجر، ونسب التغطية.',
    icon: ClipboardDocumentListIcon,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400',
  },
  {
    id: 'whatsapp_messages',
    title: 'سجل رسائل ومحادثات الواتساب',
    shortTitle: 'رسائل الواتساب',
    description: 'سجل تفصيلي لجميع الرسائل الصادرة والواردة ونصوصها وحالات تسليمها وقراءتها.',
    icon: ChatBubbleLeftRightIcon,
    color: 'text-green-600 bg-green-50 dark:bg-green-950/40 dark:text-green-400',
  },
  {
    id: 'questionnaire_answers',
    title: 'تقرير إجابات الاستبيان التفصيلي',
    shortTitle: 'إجابات الاستبيان',
    description: 'سجل إجابات السائقين على كافة أسئلة الاستبيان، نوع الإجابة، وما إذا تم تخطي السؤال.',
    icon: DocumentTextIcon,
    color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 dark:text-indigo-400',
  },
  {
    id: 'group_performance',
    title: 'تقرير أداء مجموعات الواتساب',
    shortTitle: 'أداء المجموعات',
    description: 'تحليل عائد الاستقطاب من مجموعات الواتساب ونسب التحويل من منضمين إلى سائقين نشطين.',
    icon: ChartBarIcon,
    color: 'text-purple-600 bg-purple-50 dark:bg-purple-950/40 dark:text-purple-400',
  },
  {
    id: 'needs_review',
    title: 'تقرير السائقين المطلوب مراجعتهم',
    shortTitle: 'بحاجة لمراجعة',
    description: 'قائمة السائقين المتعثرين في الاستبيان أو الذين وُضعت عليهم علامة تتطلب مراجعة بشرية.',
    icon: ExclamationTriangleIcon,
    color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400',
  },
  {
    id: 'dispatched_offers',
    title: 'سجل عروض الرحلات واستجابة السائقين',
    shortTitle: 'توزيع الرحلات',
    description: 'متابعة عروض أوامر الشغل المرسلة للسائقين ومعدلات القبول والاعتذار وتوقيت الاستجابة.',
    icon: PaperAirplaneIcon,
    color: 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/40 dark:text-cyan-400',
  },
];

export const ReportsPage: React.FC = () => {
  const addToast = useUiStore((state) => state.addToast);

  // Active Report
  const [activeReportId, setActiveReportId] = useState<string>('drivers');

  // Filter States
  const [searchTerm, setSearchTerm] = useState<string>('');
  const [dateFilterType, setDateFilterType] = useState<string>('30d');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [directionFilter, setDirectionFilter] = useState<string>('');
  const [skippedFilter, setSkippedFilter] = useState<string>('');

  // Pagination States
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [perPage, setPerPage] = useState<number>(25);

  // Data & Loading States
  const [tableData, setTableData] = useState<ReportTableData | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isDownloading, setIsDownloading] = useState<boolean>(false);

  // Active Report Meta
  const currentReportMeta = useMemo(() => {
    return REPORT_TABS.find((t) => t.id === activeReportId) || REPORT_TABS[0];
  }, [activeReportId]);

  // Compute Date Parameters
  const calculateDateRange = useCallback((): { start_date?: string; end_date?: string } => {
    const today = new Date();
    const formatDate = (d: Date) => d.toISOString().split('T')[0];

    switch (dateFilterType) {
      case 'today': {
        const dStr = formatDate(today);
        return { start_date: dStr, end_date: dStr };
      }
      case 'yesterday': {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        const yStr = formatDate(y);
        return { start_date: yStr, end_date: yStr };
      }
      case 'this_week': {
        const d = new Date(today);
        const day = d.getDay();
        const diff = (day + 1) % 7;
        d.setDate(d.getDate() - diff);
        return { start_date: formatDate(d), end_date: formatDate(today) };
      }
      case 'this_month': {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        return { start_date: formatDate(firstDay), end_date: formatDate(today) };
      }
      case '30d': {
        const d = new Date(today);
        d.setDate(d.getDate() - 30);
        return { start_date: formatDate(d), end_date: formatDate(today) };
      }
      case 'custom': {
        const params: { start_date?: string; end_date?: string } = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return params;
      }
      case 'all':
      default:
        return {};
    }
  }, [dateFilterType, startDate, endDate]);

  // Fetch report data
  const fetchReportData = useCallback(async () => {
    setIsLoading(true);
    const dateParams = calculateDateRange();

    const queryParams: ReportQueryParams = {
      ...dateParams,
      search: searchTerm.trim() || undefined,
      page: currentPage,
      per_page: perPage,
    };

    if (activeReportId === 'whatsapp_messages' && directionFilter) {
      queryParams.direction = directionFilter;
    }
    if (activeReportId === 'questionnaire_answers' && skippedFilter) {
      queryParams.is_skipped = skippedFilter === 'skipped' ? true : false;
    }

    try {
      const data = await reportService.getReportData(activeReportId, queryParams);
      setTableData(data);
    } catch (e: any) {
      addToast({
        title: ar.errors.generalError,
        message: 'فشل تحميل بيانات التقرير، يرجى إعادة المحاولة',
        type: 'error',
      });
    } finally {
      setIsLoading(false);
    }
  }, [
    activeReportId,
    calculateDateRange,
    searchTerm,
    currentPage,
    perPage,
    directionFilter,
    skippedFilter,
    addToast,
  ]);

  // Trigger fetch when report, page, or filters change
  useEffect(() => {
    fetchReportData();
  }, [fetchReportData]);

  // Reset page when switching report or filter
  const handleSwitchReport = (id: string) => {
    setActiveReportId(id);
    setCurrentPage(1);
    setSearchTerm('');
    setDirectionFilter('');
    setSkippedFilter('');
  };

  // Instant direct Excel Download
  const handleDownloadExcel = async () => {
    setIsDownloading(true);
    const dateParams = calculateDateRange();
    const queryParams: ReportQueryParams = {
      ...dateParams,
      search: searchTerm.trim() || undefined,
    };

    if (activeReportId === 'whatsapp_messages' && directionFilter) {
      queryParams.direction = directionFilter;
    }
    if (activeReportId === 'questionnaire_answers' && skippedFilter) {
      queryParams.is_skipped = skippedFilter === 'skipped' ? true : false;
    }

    try {
      const dateSuffix = new Date().toISOString().split('T')[0];
      const filename = `تقرير_${currentReportMeta.shortTitle}_${dateSuffix}.xlsx`;
      await reportService.downloadReport(activeReportId, queryParams, filename);
      addToast({
        title: 'تم التنزيل بنجاح',
        message: `تم تحميل ملف Excel (${currentReportMeta.title}) بنجاح.`,
        type: 'success',
      });
    } catch (e: any) {
      addToast({
        title: ar.errors.generalError,
        message: 'حدث خطأ أثناء تنزيل التقرير، يرجى المحاولة لاحقاً',
        type: 'error',
      });
    } finally {
      setIsDownloading(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <PageContainer className="space-y-6 print:p-0">
      {/* Top Header */}
      <PageHeader
        title="التقارير الشاملة والمعاينة الحية"
        description="استعراض تفصيلي لجداول البيانات التشغيلية مع المعاينة الفورية والتصدير المباشر لملفات Excel"
        actions={
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              color="default"
              startContent={<DocumentTextIcon className="h-4 w-4" />}
              onPress={handlePrint}
              className="hidden sm:inline-flex"
            >
              طباعة الجدول
            </Button>
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<ArrowPathIcon className="h-4 w-4" />}
              onPress={fetchReportData}
              isLoading={isLoading}
            >
              تحديث
            </Button>
          </div>
        }
      />

      {/* Report Switcher Navigation Tabs */}
      <Card className="border border-default-200 shadow-sm bg-content1 print:hidden">
        <CardBody className="p-2 sm:p-3">
          <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
            {REPORT_TABS.map((tab) => {
              const Icon = tab.icon;
              const isActive = activeReportId === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => handleSwitchReport(tab.id)}
                  className={`flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl font-bold text-xs whitespace-nowrap transition-all border ${
                    isActive
                      ? 'bg-primary text-white border-primary shadow-sm shadow-primary/20'
                      : 'bg-content1 hover:bg-default-100 text-default-700 border-default-200'
                  }`}
                >
                  <div
                    className={`p-1.5 rounded-lg ${
                      isActive ? 'bg-white/20 text-white' : tab.color
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                  </div>
                  <span>{tab.shortTitle}</span>
                </button>
              );
            })}
          </div>
        </CardBody>
      </Card>

      {/* Active Report Screen Container */}
      <div className="space-y-6">
        {/* Report Banner & Quick Download Card */}
        <Card className="border border-default-200 shadow-sm bg-content1">
          <CardBody className="p-4 sm:p-5">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex items-start gap-3.5">
                <div className={`p-3.5 rounded-2xl ${currentReportMeta.color}`}>
                  {React.createElement(currentReportMeta.icon, { className: 'w-7 h-7' })}
                </div>
                <div>
                  <div className="flex items-center gap-2 flex-wrap">
                    <h2 className="text-xl font-black text-default-900">
                      {currentReportMeta.title}
                    </h2>
                    <Chip size="sm" variant="flat" color="primary" className="font-bold text-xs">
                      {tableData?.meta?.total ?? 0} سجل
                    </Chip>
                    <Chip size="sm" variant="bordered" color="default" className="text-xs">
                      Excel (.xlsx)
                    </Chip>
                  </div>
                  <p className="text-xs text-default-500 mt-1 max-w-2xl leading-relaxed">
                    {currentReportMeta.description}
                  </p>
                </div>
              </div>

              {/* Instant Download Button */}
              <div className="flex items-center gap-2 self-start md:self-auto">
                <Button
                  color="primary"
                  variant="solid"
                  size="md"
                  className="font-bold shadow-md shadow-primary/25"
                  startContent={<ArrowDownTrayIcon className="w-5 h-5" />}
                  onPress={handleDownloadExcel}
                  isLoading={isDownloading}
                >
                  تحميل ملف Excel (.xlsx)
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Real Summary KPI Metrics (Real database values only, NO graphs) */}
        {tableData?.summary && Object.keys(tableData.summary).length > 0 && (
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            {Object.entries(tableData.summary).map(([key, value]) => {
              const labelMap: Record<string, string> = {
                total: 'إجمالي السجلات',
                active: 'نشط ومعتمد',
                in_progress: 'في مرحلة الاستبيان',
                needs_review: 'بحاجة لمراجعة ⚠️',
                activation_rate: 'معدل التفعيل',
                total_orders: 'إجمالي أوامر الشغل',
                trips_needed: 'الرحلات المطلوبة',
                confirmed_count: 'السائقين المؤكدين',
                fulfillment_rate: 'نسبة التغطية الكلية',
                inbound: 'رسائل واردة 📥',
                outbound: 'رسائل صادرة 📤',
                delivered: 'تم التسليم للهاتف',
                read: 'تمت القراءة ✓✓',
                delivery_rate: 'معدل التسليم',
                answered: 'إجابات مكتملة',
                skipped: 'أسئلة متخطاة',
                completion_rate: 'معدل الإكمال',
                total_groups: 'إجمالي المجموعات',
                total_drivers: 'إجمالي المسجلين',
                total_active: 'سائقون نشطون',
                avg_conversion_rate: 'متوسط معدل التحويل',
                with_retries: 'تعثروا بالاستبيان',
                manual_flags: 'تحديد يدوي',
                confirmed: 'عروض مقبولة',
                declined: 'عروض معتذر عنها',
                pending: 'بانتظار الرد',
                acceptance_rate: 'معدل القبول',
              };

              return (
                <Card
                  key={key}
                  className="border border-default-200 shadow-sm bg-content1 hover:border-primary/40 transition-colors"
                >
                  <CardBody className="p-3.5 sm:p-4">
                    <p className="text-[11px] font-semibold text-default-500">
                      {labelMap[key] || key}
                    </p>
                    <h3 className="text-xl sm:text-2xl font-black text-default-900 mt-1">
                      {typeof value === 'number' ? value.toLocaleString() : value}
                    </h3>
                  </CardBody>
                </Card>
              );
            })}
          </div>
        )}

        {/* Filters and Search Toolbar */}
        <Card className="border border-default-200 shadow-sm bg-content1 print:hidden">
          <CardBody className="p-3 sm:p-4 space-y-3">
            {/* Date Presets Row */}
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
              <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span className="text-xs font-bold text-default-700 flex items-center gap-1.5 ms-1">
                  <CalendarIcon className="h-4 w-4 text-primary" />
                  الفترة:
                </span>
                {[
                  { id: 'today', label: 'اليوم' },
                  { id: 'yesterday', label: 'أمس' },
                  { id: 'this_week', label: 'هذا الأسبوع' },
                  { id: 'this_month', label: 'هذا الشهر' },
                  { id: '30d', label: 'آخر 30 يوم' },
                  { id: 'all', label: 'كل الفترات' },
                  { id: 'custom', label: 'مخصص 📅' },
                ].map((btn) => (
                  <Button
                    key={btn.id}
                    size="sm"
                    variant={dateFilterType === btn.id ? 'solid' : 'flat'}
                    color={dateFilterType === btn.id ? 'primary' : 'default'}
                    onPress={() => {
                      setDateFilterType(btn.id);
                      setCurrentPage(1);
                    }}
                    className="font-medium text-xs h-8 px-3"
                  >
                    {btn.label}
                  </Button>
                ))}
              </div>

              {dateFilterType === 'custom' && (
                <div className="flex flex-wrap items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-default-100">
                  <Input
                    type="date"
                    value={startDate}
                    onValueChange={(val) => {
                      setStartDate(val);
                      setCurrentPage(1);
                    }}
                    size="sm"
                    variant="bordered"
                    className="w-36"
                    aria-label="من تاريخ"
                  />
                  <Input
                    type="date"
                    value={endDate}
                    onValueChange={(val) => {
                      setEndDate(val);
                      setCurrentPage(1);
                    }}
                    size="sm"
                    variant="bordered"
                    className="w-36"
                    aria-label="إلى تاريخ"
                  />
                </div>
              )}
            </div>

            {/* Search Input and Specific Filters */}
            <div className="flex flex-col sm:flex-row items-center gap-3 pt-2 border-t border-default-100">
              <div className="w-full sm:flex-1">
                <Input
                  size="sm"
                  variant="bordered"
                  placeholder="بحث سريع في بيانات هذا التقرير (الاسم، الهاتف، الكود، المحتوى...)"
                  value={searchTerm}
                  onValueChange={(val) => {
                    setSearchTerm(val);
                    setCurrentPage(1);
                  }}
                  startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />}
                  isClearable
                  onClear={() => setSearchTerm('')}
                  aria-label="بحث في بيانات التقرير"
                />
              </div>

              {/* Specific Filter for WhatsApp Messages */}
              {activeReportId === 'whatsapp_messages' && (
                <div className="w-full sm:w-44">
                  <Select
                    size="sm"
                    variant="bordered"
                    placeholder="اتجاه الرسائل"
                    selectedKeys={directionFilter ? [directionFilter] : []}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0] as string;
                      setDirectionFilter(selected || '');
                      setCurrentPage(1);
                    }}
                    aria-label="اتجاه الرسائل"
                  >
                    <SelectItem key="" textValue="كل الاتجاهات">
                      كل الاتجاهات
                    </SelectItem>
                    <SelectItem key="in" textValue="وارد من السائق 📥">
                      وارد من السائق 📥
                    </SelectItem>
                    <SelectItem key="out" textValue="صادر للنظام 📤">
                      صادر للنظام 📤
                    </SelectItem>
                  </Select>
                </div>
              )}

              {/* Specific Filter for Questionnaire Answers */}
              {activeReportId === 'questionnaire_answers' && (
                <div className="w-full sm:w-44">
                  <Select
                    size="sm"
                    variant="bordered"
                    placeholder="حالة السؤال"
                    selectedKeys={skippedFilter ? [skippedFilter] : []}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0] as string;
                      setSkippedFilter(selected || '');
                      setCurrentPage(1);
                    }}
                    aria-label="حالة السؤال"
                  >
                    <SelectItem key="" textValue="كل الحالات">
                      كل الحالات
                    </SelectItem>
                    <SelectItem key="answered" textValue="مكتملة ✓">
                      مكتملة ✓
                    </SelectItem>
                    <SelectItem key="skipped" textValue="تم التخطي ✕">
                      تم التخطي ✕
                    </SelectItem>
                  </Select>
                </div>
              )}
            </div>
          </CardBody>
        </Card>

        {/* Live Data Preview Table */}
        <Card className="border border-default-200 shadow-sm bg-content1 overflow-hidden">
          <CardHeader className="p-4 pb-2 flex items-center justify-between border-b border-default-100 bg-default-50/50">
            <div>
              <h3 className="font-bold text-sm text-default-900 flex items-center gap-2">
                <span>معاينة البيانات الحية</span>
                <span className="text-xs text-default-400 font-normal">
                  ({tableData?.meta?.total ?? 0} سجل مطابق)
                </span>
              </h3>
            </div>
            <div className="flex items-center gap-2 text-xs text-default-500 font-mono">
              صفحة {tableData?.meta?.current_page || 1} من {tableData?.meta?.last_page || 1}
            </div>
          </CardHeader>

          <CardBody className="p-0">
            {isLoading ? (
              <div className="flex flex-col items-center justify-center py-24 space-y-3">
                <Spinner size="lg" color="primary" />
                <p className="text-xs font-semibold text-default-500">جاري استرجاع بيانات التقرير...</p>
              </div>
            ) : tableData && tableData.rows.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-start text-xs">
                  <thead className="bg-default-100 text-default-700 font-bold border-b border-default-200 select-none">
                    <tr>
                      {tableData.columns.map((col) => (
                        <th
                          key={col.key}
                          className={`py-3.5 px-4 text-${col.align || 'start'} whitespace-nowrap`}
                        >
                          {col.label}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-default-100">
                    {tableData.rows.map((row, rowIdx) => (
                      <tr
                        key={row.id || rowIdx}
                        className="hover:bg-default-50/80 transition-colors"
                      >
                        {tableData.columns.map((col) => {
                          const val = row[col.key];

                          // Custom Badge Rendering for Status & Direction
                          if (col.key === 'status_label') {
                            const isSuccess =
                              val === 'شغال معانا' ||
                              val === 'مؤكد' ||
                              val === 'مكتمل' ||
                              val === 'وصلت' ||
                              val === 'قُرئت';
                            const isWarning =
                              val === 'في الاستبيان' ||
                              val === 'مهتم' ||
                              val === 'قيد التنفيذ' ||
                              val === 'قيد الإرسال';
                            const isDanger =
                              val === 'معتذر' ||
                              val === 'ملغي' ||
                              val === 'رفض' ||
                              val === 'محظور' ||
                              val === 'فشلت' ||
                              val === 'فشل الإرسال';

                            return (
                              <td
                                key={col.key}
                                className={`py-3 px-4 text-${col.align || 'start'} whitespace-nowrap`}
                              >
                                <Chip
                                  size="sm"
                                  variant="flat"
                                  color={
                                    isSuccess
                                      ? 'success'
                                      : isWarning
                                      ? 'warning'
                                      : isDanger
                                      ? 'danger'
                                      : 'default'
                                  }
                                  className="text-[11px] font-semibold h-5"
                                >
                                  {val}
                                </Chip>
                              </td>
                            );
                          }

                          if (col.key === 'needs_review_badge') {
                            return (
                              <td
                                key={col.key}
                                className={`py-3 px-4 text-${col.align || 'start'} whitespace-nowrap`}
                              >
                                <Chip
                                  size="sm"
                                  variant="flat"
                                  color={row.needs_review ? 'warning' : 'default'}
                                  className="text-[10px] font-bold h-5"
                                >
                                  {val}
                                </Chip>
                              </td>
                            );
                          }

                          if (col.key === 'skipped_label') {
                            const isSkipped = val === 'تم التخطي';
                            return (
                              <td
                                key={col.key}
                                className={`py-3 px-4 text-${col.align || 'start'} whitespace-nowrap`}
                              >
                                <Chip
                                  size="sm"
                                  variant="flat"
                                  color={isSkipped ? 'danger' : 'success'}
                                  className="text-[10px] font-semibold h-5"
                                >
                                  {val}
                                </Chip>
                              </td>
                            );
                          }

                          return (
                            <td
                              key={col.key}
                              className={`py-3 px-4 text-${col.align || 'start'} text-default-800 ${
                                col.key === 'created_at' ||
                                col.key === 'scheduled_at' ||
                                col.key === 'sent_at' ||
                                col.key === 'responded_at' ||
                                col.key === 'answered_at'
                                  ? 'font-mono text-default-500 text-[11px]'
                                  : ''
                              }`}
                            >
                              {val !== undefined && val !== null && val !== '' ? String(val) : '—'}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-20 text-center px-4">
                <div className="p-4 rounded-2xl bg-default-100 text-default-400 mb-3">
                  <MagnifyingGlassIcon className="w-8 h-8" />
                </div>
                <h4 className="font-bold text-sm text-default-800">لا توجد بيانات مطابقة للبحث أو الفترة</h4>
                <p className="text-xs text-default-500 mt-1 max-w-sm">
                  جرب تغيير نطاق التاريخ أو تفريغ نص البحث لاستعراض كافة السجلات.
                </p>
              </div>
            )}
          </CardBody>

          {/* Table Pagination Footer */}
          {tableData && tableData.meta && (
            <div className="p-3.5 border-t border-default-100 bg-default-50/50 flex flex-col sm:flex-row items-center justify-between gap-3">
              <div className="flex items-center gap-3 text-xs text-default-500">
                <span>
                  عرض {tableData.rows.length} سجل من إجمالي {tableData.meta.total.toLocaleString()} سجل
                </span>
                <div className="flex items-center gap-1.5 ms-2">
                  <span>سجل بالصفحة:</span>
                  {[25, 50, 100].map((size) => (
                    <button
                      key={size}
                      onClick={() => {
                        setPerPage(size);
                        setCurrentPage(1);
                      }}
                      className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                        perPage === size
                          ? 'bg-primary text-white'
                          : 'bg-default-200 text-default-700 hover:bg-default-300'
                      }`}
                    >
                      {size}
                    </button>
                  ))}
                </div>
              </div>
              {tableData.meta.last_page > 1 && (
                <Pagination
                  total={tableData.meta.last_page}
                  page={currentPage}
                  onChange={setCurrentPage}
                  color="primary"
                  size="sm"
                  showControls
                />
              )}
            </div>
          )}
        </Card>
      </div>
    </PageContainer>
  );
};

export default ReportsPage;
