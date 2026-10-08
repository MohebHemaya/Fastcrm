import React, { useState, useCallback } from 'react';
import {
  Button,
  Input,
  Card,
  CardBody,
  CardHeader,
} from '@heroui/react';
import {
  UsersIcon,
  ClipboardDocumentListIcon,
  DocumentArrowDownIcon,
  CalendarIcon,
  ArrowPathIcon,
  ChartBarIcon,
} from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import { ar } from '@/i18n/ar';
import exportService from '@/services/exportService';
import type { DashboardFilterParams } from '@/types/dashboard';
import ExportModal from '@/components/exports/ExportModal';
import { useUiStore } from '@/lib/stores/useUiStore';

export const ReportsPage: React.FC = () => {
  const addToast = useUiStore((state) => state.addToast);

  // Filter states
  const [dateFilterType, setDateFilterType] = useState<string>('all');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');

  // Export modal state
  const [isExportModalOpen, setIsExportModalOpen] = useState<boolean>(false);
  const [activeExportId, setActiveExportId] = useState<number | null>(null);
  const [exportingType, setExportingType] = useState<string | null>(null);

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

  const handleTriggerExport = async (type: 'drivers' | 'work_order' | 'group_performance') => {
    setExportingType(type);
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
    } finally {
      setExportingType(null);
    }
  };

  const exportCards = [
    {
      type: 'drivers' as const,
      title: 'تقرير السائقين الكامل',
      description: 'تصدير قائمة السائقين مع أرقام الهواتف، حالات التأهيل، مناطق التغطية، وتاريخ التسجيل.',
      icon: UsersIcon,
      badge: 'Excel (.xlsx)',
      color: 'bg-blue-50 text-blue-600',
    },
    {
      type: 'work_order' as const,
      title: 'تقرير أوامر الشغل والرحلات',
      description: 'تصدير تفاصيل أوامر الشغل، الرحلات المطلوبة، السائقين المؤكدين، ومعدلات الإنجاز.',
      icon: ClipboardDocumentListIcon,
      badge: 'Excel (.xlsx)',
      color: 'bg-emerald-50 text-emerald-600',
    },
    {
      type: 'group_performance' as const,
      title: 'تقرير أداء مجموعات الواتساب',
      description: 'تصدير معدلات الاستجابة، السائقين المدعوين والنشطين، ونسب التحويل لكل مجموعة واتساب.',
      icon: ChartBarIcon,
      badge: 'Excel (.xlsx)',
      color: 'bg-purple-50 text-purple-600',
    },
  ];

  return (
    <PageContainer className="space-y-6">
      <PageHeader
        title={ar.nav.reports}
        description="إنشاء وتحميل تقارير Excel المتقدمة الخاصة بالسائقين، أوامر الشغل، ومعدلات الأداء"
        actions={
          <Button
            size="sm"
            color="primary"
            variant="flat"
            className="min-h-[44px]"
            startContent={<ArrowPathIcon className="h-4 w-4" />}
            onPress={() => {
              setActiveExportId(null);
              setIsExportModalOpen(true);
            }}
          >
            {ar.exports.recentExports}
          </Button>
        }
      />

      {/* Date Range Scope Bar */}
      <Card className="border border-default-200 shadow-sm bg-content1">
        <CardBody className="p-4">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-bold text-default-700 flex items-center gap-1.5 ms-1">
                <CalendarIcon className="h-4 w-4 text-primary" />
                نطاق تاريخ التقرير:
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
                  onPress={() => setDateFilterType(btn.id)}
                  className="font-medium text-xs min-h-[36px]"
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
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Export Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {exportCards.map((card) => {
          const Icon = card.icon;
          const isCurrentLoading = exportingType === card.type;

          return (
            <Card
              key={card.type}
              className="border border-default-200 shadow-sm bg-content1 flex flex-col justify-between transition-all hover:border-primary/50 hover:shadow-md"
            >
              <CardHeader className="flex items-start justify-between pb-2">
                <div className={`p-3 rounded-2xl ${card.color}`}>
                  <Icon className="h-6 w-6" />
                </div>
                <span className="text-[11px] font-bold text-default-400 bg-default-100 px-2.5 py-1 rounded-full">
                  {card.badge}
                </span>
              </CardHeader>
              <CardBody className="py-2">
                <h3 className="font-bold text-base text-default-900 mb-2">
                  {card.title}
                </h3>
                <p className="text-xs text-default-500 leading-relaxed">
                  {card.description}
                </p>
              </CardBody>
              <div className="p-4 pt-2 border-t border-default-100 mt-4">
                <Button
                  color="primary"
                  variant="solid"
                  className="w-full font-bold min-h-[44px]"
                  startContent={<DocumentArrowDownIcon className="h-4 w-4" />}
                  isLoading={isCurrentLoading}
                  onPress={() => handleTriggerExport(card.type)}
                >
                  توليد وتحميل التقرير
                </Button>
              </div>
            </Card>
          );
        })}
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

export default ReportsPage;
