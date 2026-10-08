import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Input,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Spinner,
  Pagination,
} from '@heroui/react';
import {
  UsersIcon,
  UserPlusIcon,
  UserMinusIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  ArrowPathIcon,
  CalendarIcon,
  ArrowsUpDownIcon,
  ChartBarIcon,
  ChartPieIcon,
  DocumentArrowDownIcon,
  CircleStackIcon,
  ServerIcon,
  CheckCircleIcon,
  XCircleIcon,
  ArrowLeftIcon,
  PlusIcon,
} from '@/components/icons';
import { SearchInput } from '@/components/form';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  CartesianGrid,
  Cell,
  PieChart,
  Pie,
} from 'recharts';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import { ar } from '@/i18n/ar';
import dashboardService from '@/services/dashboardService';
import exportService from '@/services/exportService';
import { api } from '@/lib/api';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { useUiStore } from '@/lib/stores/useUiStore';
import type { DashboardSummary, GroupPerformance, DashboardFilterParams } from '@/types/dashboard';
import ExportModal from '@/components/exports/ExportModal';

type SortField = keyof GroupPerformance;
type SortOrder = 'asc' | 'desc';

interface HealthData {
  status: string;
  time: string;
  db: string;
}

export const HomePage: React.FC = () => {
  const user = useAuthStore((state) => state.user);
  const addToast = useUiStore((state) => state.addToast);

  // Health state
  const [health, setHealth] = useState<HealthData | null>(null);
  const [healthLoading, setHealthLoading] = useState<boolean>(true);

  // Dashboard data states
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [groupPerformance, setGroupPerformance] = useState<GroupPerformance[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);

  // Filter states
  const [dateFilterType, setDateFilterType] = useState<string>('all');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [groupSearch, setGroupSearch] = useState<string>('');

  // Table sorting
  const [sortField, setSortField] = useState<SortField>('imported');
  const [sortOrder, setSortOrder] = useState<SortOrder>('desc');

  // Table pagination
  const [groupCurrentPage, setGroupCurrentPage] = useState<number>(1);
  const [groupPageSize, setGroupPageSize] = useState<number>(10);

  // Export modal state
  const [isExportModalOpen, setIsExportModalOpen] = useState<boolean>(false);
  const [activeExportId, setActiveExportId] = useState<number | null>(null);

  const calculateDateRange = useCallback((type: string): DashboardFilterParams => {
    const today = new Date();
    const formatDate = (d: Date) => d.toISOString().split('T')[0];

    switch (type) {
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
      case 'this_month':
      case 'month': {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        return { start_date: formatDate(firstDay), end_date: formatDate(today) };
      }
      case 'this_quarter': {
        const currentQuarter = Math.floor(today.getMonth() / 3);
        const firstDayOfQuarter = new Date(today.getFullYear(), currentQuarter * 3, 1);
        return { start_date: formatDate(firstDayOfQuarter), end_date: formatDate(today) };
      }
      case '7d': {
        const d = new Date(today);
        d.setDate(d.getDate() - 7);
        return { start_date: formatDate(d), end_date: formatDate(today) };
      }
      case '30d': {
        const d = new Date(today);
        d.setDate(d.getDate() - 30);
        return { start_date: formatDate(d), end_date: formatDate(today) };
      }
      case '90d': {
        const d = new Date(today);
        d.setDate(d.getDate() - 90);
        return { start_date: formatDate(d), end_date: formatDate(today) };
      }
      case 'custom': {
        const params: DashboardFilterParams = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return params;
      }
      case 'all':
      default:
        return {};
    }
  }, [startDate, endDate]);

  const fetchHealth = async () => {
    setHealthLoading(true);
    try {
      const response = await api.get('/health');
      setHealth(response.data);
    } catch {
      // Health check failure handled gracefully
    } finally {
      setHealthLoading(false);
    }
  };

  const fetchData = useCallback(
    async (showLoading = true) => {
      if (showLoading) setIsLoading(true);
      else setIsRefreshing(true);

      const params = calculateDateRange(dateFilterType);

      try {
        const [sumRes, groupRes] = await Promise.all([
          dashboardService.getSummary(params),
          dashboardService.getGroupPerformance(params),
        ]);
        setSummary(sumRes.data);
        setGroupPerformance(groupRes.data || []);
      } catch (err: any) {
        addToast({
          type: 'error',
          message: err?.response?.data?.message || err?.message || ar.errors.networkError,
        });
      } finally {
        setIsLoading(false);
        setIsRefreshing(false);
      }
    },
    [calculateDateRange, dateFilterType, addToast]
  );

  useEffect(() => {
    fetchData(true);
    fetchHealth();
  }, [fetchData]);

  const handleDateFilterChange = (val: string) => {
    setDateFilterType(val);
  };

  const handleApplyCustomFilter = () => {
    fetchData(true);
  };

  const handleClearFilter = () => {
    setDateFilterType('all');
    setStartDate('');
    setEndDate('');
  };

  const sortedAndFilteredGroups = useMemo(() => {
    let result = [...groupPerformance];

    if (groupSearch.trim()) {
      const q = groupSearch.toLowerCase();
      result = result.filter((g) => g.group_name.toLowerCase().includes(q));
    }

    result.sort((a, b) => {
      let aVal = a[sortField];
      let bVal = b[sortField];

      if (typeof aVal === 'string') {
        return sortOrder === 'asc'
          ? (aVal as string).localeCompare(bVal as string)
          : (bVal as string).localeCompare(aVal as string);
      }

      aVal = Number(aVal) || 0;
      bVal = Number(bVal) || 0;
      return sortOrder === 'asc' ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number);
    });

    return result;
  }, [groupPerformance, groupSearch, sortField, sortOrder]);

  // Reset pagination to page 1 when filter, search, sort or page size changes
  useEffect(() => {
    setGroupCurrentPage(1);
  }, [groupSearch, dateFilterType, startDate, endDate, sortField, sortOrder, groupPageSize]);

  const totalGroups = sortedAndFilteredGroups.length;
  const totalGroupPages = Math.max(1, Math.ceil(totalGroups / groupPageSize));
  const safeCurrentPage = Math.min(Math.max(1, groupCurrentPage), totalGroupPages);

  const paginatedGroups = useMemo(() => {
    const start = (safeCurrentPage - 1) * groupPageSize;
    return sortedAndFilteredGroups.slice(start, start + groupPageSize);
  }, [sortedAndFilteredGroups, safeCurrentPage, groupPageSize]);

  const groupStartIndex = (safeCurrentPage - 1) * groupPageSize;

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('desc');
    }
  };

  const handleTriggerExport = async (type: 'drivers' | 'work_order' | 'group_performance') => {
    try {
      const filterParams = calculateDateRange(dateFilterType);
      const res = await exportService.requestExport({
        type,
        params: filterParams,
      });
      setActiveExportId(res.data.id);
      setIsExportModalOpen(true);
      addToast({
        type: 'info',
        message: ar.exports.exportStarted,
      });
    } catch (e: any) {
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    }
  };

  const statusLabels: Record<string, string> = {
    new: ar.statuses.new,
    contacted: ar.statuses.contacted,
    qualified: ar.statuses.qualified,
    training_scheduled: ar.statuses.training_scheduled,
    trained: ar.statuses.trained,
    active: ar.statuses.active,
    declined: ar.statuses.declined,
    no_response: ar.statuses.no_response,
    suspended: ar.statuses.suspended,
    blocked: ar.statuses.blocked,
    opted_out: ar.statuses.opted_out,
  };

  const statusColorMap: Record<string, string> = {
    active: '#10b981',             // Success / Emerald (نشط)
    new: '#3b82f6',                // Blue (جديد)
    contacted: '#06b6d4',          // Cyan (تم التواصل)
    qualified: '#8b5cf6',          // Purple (مؤهل)
    training_scheduled: '#f59e0b', // Amber (تدريب مجدول)
    trained: '#14b8a6',            // Teal (تم التدريب)
    declined: '#64748b',           // Slate (معتذر)
    no_response: '#94a3b8',        // Gray (لم يرد)
    suspended: '#f97316',          // Orange (موقوف مؤقتاً)
    blocked: '#ef4444',            // Red (محظور)
    opted_out: '#e11d48',          // Rose (طلب عدم التواصل)
  };

  const statusColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b'];

  const totalDrivers = summary?.total_drivers ?? (summary as any)?.drivers_summary?.total_drivers ?? 0;
  const activeDrivers = summary?.active_drivers_count ?? (summary as any)?.drivers_summary?.active_drivers ?? 0;
  const optedOutDrivers = summary?.opted_out_drivers_count ?? (summary as any)?.drivers_summary?.opted_out_drivers ?? 0;
  const totalOrders = summary?.orders_this_month?.total ?? (summary as any)?.orders_summary?.total_orders ?? 0;
  const completedOrdersCount = summary?.orders_this_month?.by_status?.completed
    ?? (summary as any)?.orders_summary?.by_status?.completed?.count
    ?? 0;
  const acceptanceRate = summary?.offer_acceptance_rate ?? (summary as any)?.dispatch_metrics?.acceptance_rate ?? 0;
  const avgTimeToFill = summary?.avg_time_to_fill_minutes ?? (summary as any)?.dispatch_metrics?.avg_time_to_fill_minutes ?? null;

  const driverChartData = useMemo(() => {
    if (!summary) return [];
    const source = summary.drivers_by_status || (summary as any).drivers_summary?.by_status;
    if (!source) return [];

    return Object.entries(source)
      .map(([status, item]: [string, any]) => {
        const val = typeof item === 'number' ? item : item?.count || 0;
        const label = typeof item === 'object' && item?.label ? item.label : statusLabels[status] || status;
        return {
          status,
          name: label,
          value: val,
          color: statusColorMap[status] || statusColors[0],
        };
      })
      .filter((item) => item.value > 0);
  }, [summary]);

  const monthlyTrendsData = useMemo(() => {
    if (summary?.monthly_trends && summary.monthly_trends.length > 0) {
      return summary.monthly_trends;
    }
    const rawTrend = (summary as any)?.monthly_trend;
    if (Array.isArray(rawTrend) && rawTrend.length > 0) {
      return rawTrend.map((m: any) => ({
        month: m.month,
        label: m.name || m.month,
        total_orders: m.total || 0,
        completed_orders: m.completed || 0,
      }));
    }
    return [];
  }, [summary]);

  const formatTimeToFill = (minutes: number | null) => {
    if (minutes === null || minutes === undefined) return ar.dashboard.cards.na;
    if (minutes < 60) {
      return `${Math.round(minutes)} ${ar.dashboard.cards.minutes}`;
    }
    const hours = (minutes / 60).toFixed(1);
    return `${hours} ${ar.dashboard.cards.hours}`;
  };

  return (
    <PageContainer className="space-y-6">
      <PageHeader
        title={ar.dashboard.title}
        description={`مرحباً بك، ${user?.name || 'المدير'} — ${ar.dashboard.subtitle}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              className="min-h-[44px]"
              startContent={<ArrowPathIcon className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />}
              onPress={() => {
                fetchData(false);
                fetchHealth();
              }}
              isDisabled={isRefreshing || isLoading}
            >
              {ar.dashboard.refresh}
            </Button>
            <Button
              as={Link}
              to="/reports"
              size="sm"
              color="primary"
              variant="flat"
              className="min-h-[44px]"
              startContent={<ChartBarIcon className="h-4 w-4" />}
            >
              {ar.nav.reports}
            </Button>
            <Button
              as={Link}
              to="/work-orders/new"
              size="sm"
              color="primary"
              variant="solid"
              className="min-h-[44px]"
              startContent={<PlusIcon className="h-4 w-4" />}
            >
              أمر شغل جديد
            </Button>
          </div>
        }
      />

      {/* Date Range Filter Bar */}
      <Card className="border border-default-200 shadow-sm bg-content1">
        <CardBody className="p-4">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-bold text-default-700 flex items-center gap-1.5 ms-1">
                <CalendarIcon className="h-4 w-4 text-primary" />
                {ar.dashboard.dateRange}:
              </span>
              {[
                { id: 'today', label: 'اليوم' },
                { id: 'yesterday', label: 'أمس' },
                { id: 'this_week', label: 'هذا الأسبوع' },
                { id: 'this_month', label: 'هذا الشهر' },
                { id: 'this_quarter', label: 'الربع الحالي' },
                { id: 'all', label: 'كل الفترات' },
                { id: 'custom', label: 'مخصص 📅' },
              ].map((btn) => (
                <Button
                  key={btn.id}
                  size="sm"
                  variant={dateFilterType === btn.id ? 'solid' : 'flat'}
                  color={dateFilterType === btn.id ? 'primary' : 'default'}
                  onPress={() => handleDateFilterChange(btn.id)}
                  className="font-medium text-xs"
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
                  onValueChange={setStartDate}
                  size="sm"
                  variant="bordered"
                  className="w-36"
                  aria-label={ar.dashboard.startDate}
                />
                <Input
                  type="date"
                  value={endDate}
                  onValueChange={setEndDate}
                  size="sm"
                  variant="bordered"
                  className="w-36"
                  aria-label={ar.dashboard.endDate}
                />
                <Button
                  size="sm"
                  color="primary"
                  onPress={handleApplyCustomFilter}
                >
                  {ar.dashboard.applyFilter}
                </Button>
                <Button
                  size="sm"
                  variant="light"
                  onPress={handleClearFilter}
                >
                  {ar.dashboard.clearFilter}
                </Button>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Metric Cards Grid */}
      {isLoading ? (
        <div className="flex justify-center items-center py-20">
          <Spinner size="lg" label={ar.app.loading} />
        </div>
      ) : summary ? (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            {/* Total Drivers */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.totalDrivers}
                  </span>
                  <div className="p-2 rounded-xl bg-blue-50 text-blue-600">
                    <UsersIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2">
                  <span className="text-2xl font-black text-default-900" dir="ltr">
                    {totalDrivers.toLocaleString('en-US')}
                  </span>
                </div>
              </CardBody>
            </Card>

            {/* Active Drivers */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.activeDrivers}
                  </span>
                  <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <UserPlusIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2 flex items-baseline gap-2">
                  <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400" dir="ltr">
                    {activeDrivers.toLocaleString('en-US')}
                  </span>
                  {totalDrivers > 0 && (
                    <span className="text-[11px] text-default-400 font-semibold" dir="ltr">
                      ({((activeDrivers / totalDrivers) * 100).toFixed(0)}%)
                    </span>
                  )}
                </div>
              </CardBody>
            </Card>

            {/* Opted-Out Drivers */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.optedOutDrivers}
                  </span>
                  <div className="p-2 rounded-xl bg-rose-50 text-rose-600">
                    <UserMinusIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2">
                  <span className="text-2xl font-black text-rose-600" dir="ltr">
                    {optedOutDrivers.toLocaleString('en-US')}
                  </span>
                </div>
              </CardBody>
            </Card>

            {/* Orders This Month */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.ordersThisMonth}
                  </span>
                  <div className="p-2 rounded-xl bg-amber-50 text-amber-600">
                    <ClipboardDocumentListIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2 flex items-baseline justify-between">
                  <span className="text-2xl font-black text-default-900" dir="ltr">
                    {totalOrders.toLocaleString('en-US')}
                  </span>
                  <div className="flex gap-1 text-[10px]">
                    <span className="text-success font-bold">
                      {completedOrdersCount} مكتمل
                    </span>
                  </div>
                </div>
              </CardBody>
            </Card>

            {/* Offer Acceptance Rate */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.acceptanceRate}
                  </span>
                  <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                    <ChartBarIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2">
                  <span className="text-2xl font-black text-indigo-600" dir="ltr">
                    {acceptanceRate}%
                  </span>
                </div>
              </CardBody>
            </Card>

            {/* Average Time-To-Fill */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardBody className="p-4 flex flex-col justify-between">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-default-500 font-medium">
                    {ar.dashboard.cards.avgTimeToFill}
                  </span>
                  <div className="p-2 rounded-xl bg-purple-50 text-purple-600">
                    <ClockIcon className="h-4 w-4" />
                  </div>
                </div>
                <div className="mt-2">
                  <span className="text-lg font-black text-purple-700">
                    {formatTimeToFill(avgTimeToFill)}
                  </span>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* Charts Row */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Monthly Work Orders Trend Chart */}
            <Card className="lg:col-span-2 border border-default-200 shadow-sm bg-content1">
              <CardHeader className="flex items-center justify-between pb-2 border-b border-default-100">
                <div className="flex items-center gap-2">
                  <ChartBarIcon className="h-4 w-4 text-primary" />
                  <span className="font-bold text-sm text-default-900">
                    {ar.dashboard.charts.monthlyOrders}
                  </span>
                </div>
              </CardHeader>
              <CardBody className="p-4 pt-6">
                {monthlyTrendsData.length > 0 ? (
                  <div className="h-64 w-full" dir="ltr">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={monthlyTrendsData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} opacity={0.3} />
                        <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                        <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                        <Tooltip />
                        <Legend wrapperStyle={{ fontSize: '12px', paddingTop: '10px' }} />
                        <Bar
                          dataKey="total_orders"
                          name={ar.dashboard.charts.totalOrders}
                          fill="#3b82f6"
                          radius={[4, 4, 0, 0]}
                        />
                        <Bar
                          dataKey="completed_orders"
                          name={ar.dashboard.charts.completedOrders}
                          fill="#10b981"
                          radius={[4, 4, 0, 0]}
                        />
                      </BarChart>
                    </ResponsiveContainer>
                  </div>
                ) : (
                  <div className="h-64 flex items-center justify-center text-default-400 text-sm">
                    {ar.common.noData}
                  </div>
                )}
              </CardBody>
            </Card>

            {/* Driver Status Breakdown Pie Chart */}
            <Card className="border border-default-200 shadow-sm bg-content1">
              <CardHeader className="flex items-center justify-between pb-2 border-b border-default-100">
                <div className="flex items-center gap-2">
                  <ChartPieIcon className="h-4 w-4 text-primary" />
                  <span className="font-bold text-sm text-default-900">
                    {ar.dashboard.charts.driversStatus}
                  </span>
                </div>
              </CardHeader>
              <CardBody className="p-4 pt-6">
                {driverChartData.length > 0 ? (
                  <div className="h-64 w-full flex flex-col items-center justify-center" dir="ltr">
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={driverChartData}
                          dataKey="value"
                          nameKey="name"
                          cx="50%"
                          cy="50%"
                          innerRadius={50}
                          outerRadius={80}
                          paddingAngle={3}
                        >
                          {driverChartData.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={entry.color} />
                          ))}
                        </Pie>
                        <Tooltip />
                        <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '5px' }} />
                      </PieChart>
                    </ResponsiveContainer>
                  </div>
                ) : (
                  <div className="h-64 flex items-center justify-center text-default-400 text-sm">
                    {ar.common.noData}
                  </div>
                )}
              </CardBody>
            </Card>
          </div>

          {/* Group Performance Table Section */}
          <Card className="border border-default-200 shadow-sm bg-content1">
            <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-default-100 p-4">
              <div>
                <h3 className="font-bold text-base text-default-900">
                  {ar.dashboard.groupPerformance.title}
                </h3>
                <p className="text-xs text-default-400 mt-0.5">
                  {ar.dashboard.groupPerformance.subtitle}
                </p>
              </div>

              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <SearchInput
                  value={groupSearch}
                  onSearch={setGroupSearch}
                  placeholder={ar.dashboard.groupPerformance.searchPlaceholder}
                  className="w-full sm:w-56"
                />
                <Button
                  size="sm"
                  color="primary"
                  variant="flat"
                  className="min-h-[44px]"
                  startContent={<DocumentArrowDownIcon className="h-4 w-4" />}
                  onPress={() => handleTriggerExport('group_performance')}
                >
                  {ar.dashboard.groupPerformance.exportExcel}
                </Button>
              </div>
            </CardHeader>

            <CardBody className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-start text-xs border-collapse">
                  <thead className="bg-default-50 text-default-600 font-bold border-b border-default-200">
                    <tr>
                      <th
                        className="py-3 px-4 text-start cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('group_name')}
                      >
                        <div className="flex items-center gap-1">
                          <span>{ar.dashboard.groupPerformance.groupName}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('imported')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.imported}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('invited')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.invited}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('interested')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.interested}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('active')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.active}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('declined')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.declined}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-3 text-center cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('opted_out')}
                      >
                        <div className="flex items-center justify-center gap-1">
                          <span>{ar.dashboard.groupPerformance.optedOut}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                      <th
                        className="py-3 px-4 text-end cursor-pointer hover:text-primary transition-colors"
                        onClick={() => handleSort('conversion_rate')}
                      >
                        <div className="flex items-center justify-end gap-1">
                          <span>{ar.dashboard.groupPerformance.conversionRate}</span>
                          <ArrowsUpDownIcon className="h-3 w-3" />
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-default-100">
                    {paginatedGroups.length === 0 ? (
                      <tr>
                        <td colSpan={8} className="py-8 text-center text-default-400">
                          {ar.dashboard.groupPerformance.empty}
                        </td>
                      </tr>
                    ) : (
                      paginatedGroups.map((g) => (
                        <tr key={g.group_id} className="hover:bg-default-50/50 transition-colors">
                          <td className="py-3 px-4 font-bold text-default-900">
                            {g.group_name}
                          </td>
                          <td className="py-3 px-3 text-center font-semibold text-default-700" dir="ltr">
                            {g.imported.toLocaleString('en-US')}
                          </td>
                          <td className="py-3 px-3 text-center font-medium text-default-600" dir="ltr">
                            {g.invited.toLocaleString('en-US')}
                          </td>
                          <td className="py-3 px-3 text-center font-medium text-blue-600" dir="ltr">
                            {g.interested.toLocaleString('en-US')}
                          </td>
                          <td className="py-3 px-3 text-center" dir="ltr">
                            <span className="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                              {g.active.toLocaleString('en-US')}
                            </span>
                          </td>
                          <td className="py-3 px-3 text-center font-medium text-amber-600" dir="ltr">
                            {g.declined.toLocaleString('en-US')}
                          </td>
                          <td className="py-3 px-3 text-center font-medium text-rose-600" dir="ltr">
                            {g.opted_out.toLocaleString('en-US')}
                          </td>
                          <td className="py-3 px-4 text-end font-mono">
                            <Chip
                              size="sm"
                              color={
                                g.conversion_rate >= 25
                                  ? 'success'
                                  : g.conversion_rate >= 10
                                  ? 'primary'
                                  : 'default'
                              }
                              variant="flat"
                              className="font-bold"
                            >
                              <span dir="ltr">{g.conversion_rate}%</span>
                            </Chip>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Group Performance Pagination Bar */}
              {sortedAndFilteredGroups.length > 0 && (
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-default-100 bg-default-50/40">
                  <div data-testid="group-pagination-summary" className="flex flex-wrap items-center gap-3 text-xs text-default-500">
                    <span>
                      عرض{' '}
                      <strong className="font-semibold text-default-800" dir="ltr">
                        {groupStartIndex + 1}
                      </strong>{' '}
                      إلى{' '}
                      <strong className="font-semibold text-default-800" dir="ltr">
                        {Math.min(groupStartIndex + groupPageSize, totalGroups)}
                      </strong>{' '}
                      من أصل{' '}
                      <strong className="font-semibold text-default-800" dir="ltr">
                        {totalGroups}
                      </strong>{' '}
                      مجموعة
                    </span>

                    <div className="flex items-center gap-1.5 ms-2">
                      <span className="text-default-400">الصفوف:</span>
                      <select
                        aria-label="عدد الصفوف في الصفحة"
                        value={groupPageSize}
                        onChange={(e) => {
                          setGroupPageSize(Number(e.target.value));
                          setGroupCurrentPage(1);
                        }}
                        className="text-xs bg-content1 border border-default-200 rounded-md px-2 py-1 text-default-700 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                      >
                        <option value={5}>5</option>
                        <option value={10}>10</option>
                        <option value={20}>20</option>
                        <option value={50}>50</option>
                      </select>
                    </div>
                  </div>

                  {totalGroupPages > 1 && (
                    <div className="flex items-center justify-center">
                      <Pagination
                        total={totalGroupPages}
                        page={safeCurrentPage}
                        onChange={setGroupCurrentPage}
                        showControls
                        size="sm"
                        color="primary"
                        classNames={{
                          cursor: 'bg-primary text-primary-foreground font-bold shadow-sm',
                        }}
                      />
                    </div>
                  )}
                </div>
              )}
            </CardBody>
          </Card>
        </>
      ) : (
        <div className="text-center py-16 text-default-400">
          <p>{ar.common.noData}</p>
        </div>
      )}

      {/* System Health Section */}
      <div className="rounded-2xl border border-default-200 bg-content1 p-6 shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-5">
          <div>
            <h2 className="text-lg font-bold text-default-900">{ar.health.status}</h2>
            <p className="text-xs text-default-500">حالة الربط بين الخادم، قاعدة البيانات، والمجدول الزمني</p>
          </div>
          <Button
            as={Link}
            to="/health"
            size="sm"
            variant="flat"
            color="primary"
            endContent={<ArrowLeftIcon className="h-3.5 w-3.5 rtl:rotate-0 ltr:rotate-180" />}
          >
            عرض تفاصيل النظام
          </Button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Backend API */}
          <Card className="border border-default-200 shadow-none bg-default-50/50">
            <CardHeader className="flex gap-3 items-center pb-2">
              <div className="p-2 rounded-xl bg-blue-100 text-blue-700">
                <ServerIcon className="h-4 w-4" />
              </div>
              <div className="flex flex-col text-start">
                <span className="text-xs font-bold text-default-900">واجهة الخادم (API)</span>
                <span className="text-[11px] text-default-400">Laravel /api/v1</span>
              </div>
            </CardHeader>
            <CardBody className="pt-2">
              {healthLoading ? (
                <div className="flex items-center gap-2 text-default-500 text-xs">
                  <Spinner size="sm" />
                  <span>{ar.app.loading}</span>
                </div>
              ) : health ? (
                <div className="flex items-center gap-2">
                  <CheckCircleIcon className="h-4 w-4 text-success" />
                  <span className="text-xs font-semibold text-success">{ar.health.operational}</span>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <XCircleIcon className="h-4 w-4 text-danger" />
                  <span className="text-xs font-semibold text-danger">{ar.health.fail}</span>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Database */}
          <Card className="border border-default-200 shadow-none bg-default-50/50">
            <CardHeader className="flex gap-3 items-center pb-2">
              <div className="p-2 rounded-xl bg-emerald-100 text-emerald-700">
                <CircleStackIcon className="h-4 w-4" />
              </div>
              <div className="flex flex-col text-start">
                <span className="text-xs font-bold text-default-900">{ar.health.database}</span>
                <span className="text-[11px] text-default-400">MySQL InnoDB utf8mb4</span>
              </div>
            </CardHeader>
            <CardBody className="pt-2">
              {healthLoading ? (
                <div className="flex items-center gap-2 text-default-500 text-xs">
                  <Spinner size="sm" />
                  <span>{ar.app.loading}</span>
                </div>
              ) : health?.db === 'ok' ? (
                <div className="flex items-center gap-2">
                  <CheckCircleIcon className="h-4 w-4 text-success" />
                  <span className="text-xs font-semibold text-success">{ar.health.ok}</span>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <XCircleIcon className="h-4 w-4 text-danger" />
                  <span className="text-xs font-semibold text-danger">{ar.health.fail}</span>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Server Time */}
          <Card className="border border-default-200 shadow-none bg-default-50/50">
            <CardHeader className="flex gap-3 items-center pb-2">
              <div className="p-2 rounded-xl bg-purple-100 text-purple-700">
                <ClockIcon className="h-4 w-4" />
              </div>
              <div className="flex flex-col text-start">
                <span className="text-xs font-bold text-default-900">توقيت الخادم</span>
                <span className="text-[11px] text-default-400">Africa/Cairo</span>
              </div>
            </CardHeader>
            <CardBody className="pt-2">
              {healthLoading ? (
                <div className="flex items-center gap-2 text-default-500 text-xs">
                  <Spinner size="sm" />
                  <span>{ar.app.loading}</span>
                </div>
              ) : health ? (
                <span className="font-mono text-xs text-default-700" dir="ltr">
                  {new Date(health.time).toLocaleString('ar-EG', { timeZone: 'Africa/Cairo' })}
                </span>
              ) : (
                <span className="text-xs text-default-400">غير متوفر</span>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Global Export Modal */}
      <ExportModal
        isOpen={isExportModalOpen}
        onClose={() => setIsExportModalOpen(false)}
        activeExportId={activeExportId}
      />
    </PageContainer>
  );
};

export default HomePage;
