import React, { useEffect, useState, useCallback } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Spinner,
  Tooltip,
} from '@heroui/react';
import {
  HeartIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  CircleStackIcon,
  ArrowPathIcon,
  TrashIcon,
  ServerIcon,
  ChatBubbleLeftRightIcon,
  Square3Stack3DIcon,
  ExclamationCircleIcon,
} from '@/components/icons';
import { healthService, type SystemHealthData, type FailedJobItem } from '@/services/healthService';
import PageContainer from '@/components/layout/PageContainer';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { useUiStore } from '@/lib/stores/useUiStore';
import { ar } from '@/i18n/ar';

export const HealthPage: React.FC = () => {
  const [data, setData] = useState<SystemHealthData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [actionInProgress, setActionInProgress] = useState<number | string | null>(null);
  const [isFlushModalOpen, setIsFlushModalOpen] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  const fetchHealth = useCallback(async (isManual = false) => {
    if (isManual) setRefreshing(true);
    try {
      const res = await healthService.getSystemHealth();
      setData(res.data);
    } catch {
      addToast({
        type: 'error',
        message: 'فشل في جلب تقرير حالة النظام.',
      });
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [addToast]);

  useEffect(() => {
    fetchHealth();
    // Auto-poll health status every 30 seconds
    const interval = setInterval(() => {
      fetchHealth();
    }, 30000);
    return () => clearInterval(interval);
  }, [fetchHealth]);

  const handleRetryJob = async (id: number) => {
    setActionInProgress(id);
    try {
      await healthService.retryFailedJob(id);
      addToast({
        type: 'success',
        message: 'تمت جدولة إعادة محاولة المهمة بنجاح.',
      });
      await fetchHealth();
    } catch {
      addToast({
        type: 'error',
        message: 'فشل في إعادة محاولة المهمة.',
      });
    } finally {
      setActionInProgress(null);
    }
  };

  const handleDeleteJob = async (id: number) => {
    setActionInProgress(id);
    try {
      await healthService.deleteFailedJob(id);
      addToast({
        type: 'success',
        message: 'تم حذف المهمة الفاشلة بنجاح.',
      });
      await fetchHealth();
    } catch {
      addToast({
        type: 'error',
        message: 'فشل في حذف المهمة.',
      });
    } finally {
      setActionInProgress(null);
    }
  };

  const handleRetryAll = async () => {
    setActionInProgress('retry-all');
    try {
      const res = await healthService.retryAllFailedJobs();
      addToast({
        type: 'success',
        message: res.message || 'تمت إعادة محاولة جميع المهام الفاشلة.',
      });
      await fetchHealth();
    } catch {
      addToast({
        type: 'error',
        message: 'فشل في إعادة محاولة المهام الفاشلة.',
      });
    } finally {
      setActionInProgress(null);
    }
  };

  const handleFlushAll = async () => {
    setActionInProgress('flush-all');
    try {
      const res = await healthService.flushFailedJobs();
      addToast({
        type: 'success',
        message: res.message || 'تم حذف جميع المهام الفاشلة.',
      });
      await fetchHealth();
    } catch {
      addToast({
        type: 'error',
        message: 'فشل في حذف المهام الفاشلة.',
      });
    } finally {
      setActionInProgress(null);
    }
  };

  if (loading) {
    return (
      <div className="flex h-96 w-full items-center justify-center">
        <Spinner size="lg" label="جاري فحص حالة النظام والخدمات..." />
      </div>
    );
  }

  if (!data) {
    return (
      <div className="flex flex-col items-center justify-center p-12 text-center" dir="rtl">
        <ExclamationTriangleIcon className="h-10 w-10 text-danger mb-3" />
        <p className="text-lg font-bold text-foreground">تعذر تحميل بيانات حالة النظام</p>
        <Button className="mt-4" color="primary" variant="flat" onPress={() => fetchHealth(true)}>
          {ar.health.refresh}
        </Button>
      </div>
    );
  }

  const isHealthy = data?.status === 'healthy';
  const isSchedulerStale = data?.scheduler.is_stale;

  const failedJobColumns: ColumnDef<FailedJobItem>[] = [
    {
      id: 'name',
      header: 'المهمة (Job)',
      isPrimary: true,
      render: (job) => (
        <div>
          <span className="font-mono text-xs block text-foreground font-semibold" dir="ltr">
            {job.name}
          </span>
          <span className="text-[11px] text-default-400 font-mono" dir="ltr">
            #{job.id}
          </span>
        </div>
      ),
    },
    {
      id: 'queue',
      header: 'الطابور',
      render: (job) => (
        <Chip size="sm" variant="flat">
          {job.queue}
        </Chip>
      ),
    },
    {
      id: 'failed_at',
      header: 'وقت الفشل',
      render: (job) => (
        <span className="text-xs text-default-500 whitespace-nowrap" dir="ltr">
          {new Date(job.failed_at).toLocaleString('ar-EG')}
        </span>
      ),
    },
    {
      id: 'exception',
      header: 'تفاصيل الخطأ',
      render: (job) => (
        <p className="text-xs text-danger-600 dark:text-danger-400 font-mono line-clamp-2 max-w-md" title={job.exception_preview}>
          {job.exception_preview}
        </p>
      ),
    },
    {
      id: 'actions',
      header: 'الإجراءات',
      className: 'text-end',
      headerClassName: 'text-end',
      render: (job) => (
        <div className="flex items-center justify-end gap-1.5">
          <Tooltip content={ar.health.retry}>
            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="primary"
              className="min-h-[40px] min-w-[40px]"
              aria-label={ar.health.retry}
              data-testid={`retry-job-${job.id}`}
              isLoading={actionInProgress === job.id}
              onPress={() => handleRetryJob(job.id)}
            >
              <ArrowPathIcon className="h-4 w-4" />
            </Button>
          </Tooltip>
          <Tooltip content={ar.health.delete} color="danger">
            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="danger"
              className="min-h-[40px] min-w-[40px]"
              aria-label={ar.health.delete}
              data-testid={`delete-job-${job.id}`}
              isLoading={actionInProgress === job.id}
              onPress={() => handleDeleteJob(job.id)}
            >
              <TrashIcon className="h-4 w-4" />
            </Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  return (
    <PageContainer className="space-y-6">
      {/* Top Header & Summary */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-default-200 pb-5">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
              <HeartIcon className="h-7 w-7 text-primary" />
              {ar.health.title}
            </h1>
            <Chip
              startContent={
                isHealthy ? (
                  <CheckCircleIcon className="h-4 w-4" />
                ) : (
                  <ExclamationTriangleIcon className="h-4 w-4" />
                )
              }
              color={isHealthy ? 'success' : 'warning'}
              variant="flat"
              size="sm"
              className="font-medium"
            >
              {isHealthy ? ar.health.healthy : ar.health.warning}
            </Chip>
          </div>
          <p className="mt-1 text-sm text-default-500">{ar.health.subtitle}</p>
        </div>

        <div className="flex items-center gap-3">
          <Button
            size="sm"
            variant="flat"
            color="primary"
            className="min-h-[44px]"
            startContent={<ArrowPathIcon className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />}
            isLoading={refreshing}
            onPress={() => fetchHealth(true)}
          >
            {ar.health.refresh}
          </Button>
        </div>
      </div>

      {/* Critical Stale Heartbeat Banner */}
      {isSchedulerStale && (
        <div className="rounded-xl border border-danger-200 bg-danger-50/70 p-4 text-danger-900 shadow-none dark:bg-danger-950/40 dark:border-danger-800 dark:text-danger-200 flex items-start gap-3">
          <ExclamationCircleIcon className="h-6 w-6 text-danger-600 shrink-0 mt-0.5" />
          <div className="flex flex-col gap-1">
            <span className="font-bold text-base">
              {ar.health.schedulerStale}
            </span>
            <p className="text-sm opacity-90">
              لم تسجل أية نبضة من مجدول المهام منذ{' '}
              <span className="font-semibold ltr-text" dir="ltr">
                {data?.scheduler.age_minutes ?? 0}m
              </span>
              . يرجى التأكد من تشغيل أمر الجدولة{' '}
              <code className="bg-danger-100 dark:bg-danger-900/60 px-1.5 py-0.5 rounded text-xs font-mono" dir="ltr">
                * * * * * php artisan schedule:run
              </code>{' '}
              في لوحة تحكم الاستضافة (hPanel Cron Jobs).
            </p>
          </div>
        </div>
      )}

      {/* Grid of System Component Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* 1. Scheduler Cron Heartbeat */}
        <Card className="border border-default-200 shadow-none">
          <CardHeader className="flex justify-between items-center pb-2">
            <span className="text-sm font-medium text-default-500 flex items-center gap-1.5">
              <ClockIcon className="h-4 w-4 text-primary" />
              {ar.health.scheduler}
            </span>
            <Chip
              size="sm"
              color={data?.scheduler.status === 'ok' ? 'success' : 'danger'}
              variant="dot"
            >
              {data?.scheduler.status === 'ok' ? ar.health.schedulerActive : 'متوقف'}
            </Chip>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="text-2xl font-bold tracking-tight text-foreground ltr-text" dir="ltr">
              {data?.scheduler.age_minutes !== null ? `${data.scheduler.age_minutes}m ago` : ar.health.never}
            </div>
            <p className="text-xs text-default-400 mt-1">
              الحد الأقصى للتنبيه:{' '}
              <span className="font-medium ltr-text" dir="ltr">
                {data?.scheduler.warning_threshold_minutes}m
              </span>
            </p>
          </CardBody>
        </Card>

        {/* 2. Queue Backlog */}
        <Card className="border border-default-200 shadow-none">
          <CardHeader className="flex justify-between items-center pb-2">
            <span className="text-sm font-medium text-default-500 flex items-center gap-1.5">
              <Square3Stack3DIcon className="h-4 w-4 text-primary" />
              {ar.health.queue}
            </span>
            <Chip
              size="sm"
              color={data?.queue.status === 'ok' ? 'success' : 'warning'}
              variant="flat"
            >
              {data?.queue.backlog_size === 0 ? 'فارغ' : 'نشط'}
            </Chip>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="text-2xl font-bold tracking-tight text-foreground ltr-text" dir="ltr">
              {data?.queue.backlog_size ?? 0} jobs
            </div>
            <p className="text-xs text-default-400 mt-1">
              {data?.queue.oldest_job_age_seconds !== null
                ? `أقدم مهمة منذ ${data.queue.oldest_job_age_seconds} ثانية`
                : 'لا توجد مهام متراكمة في الانتظار'}
            </p>
          </CardBody>
        </Card>

        {/* 3. WhatsApp Gateway Status */}
        <Card className="border border-default-200 shadow-none">
          <CardHeader className="flex justify-between items-center pb-2">
            <span className="text-sm font-medium text-default-500 flex items-center gap-1.5">
              <ChatBubbleLeftRightIcon className="h-4 w-4 text-primary" />
              {ar.health.whatsappSection}
            </span>
            <Chip size="sm" variant="flat" color="default">
              {data?.whatsapp.provider}
            </Chip>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="text-sm font-semibold text-foreground flex items-center justify-between">
              <span className="text-default-500 text-xs">إصدار Graph API:</span>
              <span className="font-mono text-xs font-bold text-primary ltr-text" dir="ltr">
                {data?.whatsapp.graph_version}
              </span>
            </div>
            <div className="text-sm font-semibold text-foreground flex items-center justify-between mt-2">
              <span className="text-default-500 text-xs">رسائل فاشلة (24س):</span>
              <span
                className={`text-xs font-bold ${
                  (data?.whatsapp.failed_messages_24h ?? 0) > 0 ? 'text-danger font-bold' : 'text-success'
                }`}
              >
                {data?.whatsapp.failed_messages_24h ?? 0}
              </span>
            </div>
          </CardBody>
        </Card>

        {/* 4. Database & Backups */}
        <Card className="border border-default-200 shadow-none">
          <CardHeader className="flex justify-between items-center pb-2">
            <span className="text-sm font-medium text-default-500 flex items-center gap-1.5">
              <CircleStackIcon className="h-4 w-4 text-primary" />
              {ar.health.database}
            </span>
            <Chip size="sm" color="success" variant="flat">
              {data?.database.status === 'ok' ? 'متصل' : 'خطأ'}
            </Chip>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="text-sm font-semibold text-foreground flex items-center justify-between">
              <span className="text-default-500 text-xs">النسخ الاحتياطية:</span>
              <span className="text-xs font-bold text-foreground">
                {data?.backups.count ?? 0} نسخ
              </span>
            </div>
            <p className="text-xs text-default-400 mt-2 truncate" title={data?.backups.last_backup_at ?? ''}>
              آخر نسخة: {data?.backups.last_backup_at ? new Date(data.backups.last_backup_at).toLocaleDateString('ar-EG') : 'لا يوجد'}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Failed Jobs Management Section */}
      <Card className="border border-default-200 shadow-none mt-2">
        <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-default-200 px-6 py-4">
          <div className="flex items-center gap-3">
            <ServerIcon className="h-5 w-5 text-danger-600 shrink-0" />
            <div>
              <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
                {ar.health.failedJobs}
                <Chip size="sm" color={data?.failed_jobs.count ? 'danger' : 'default'} variant="flat">
                  {data?.failed_jobs.count ?? 0}
                </Chip>
              </h2>
              <p className="text-xs text-default-500">
                إدارة ومتابعة المهام المعلقة التي واجهت أخطاء أثناء التنفيذ
              </p>
            </div>
          </div>

          {(data?.failed_jobs.count ?? 0) > 0 && (
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant="flat"
                color="primary"
                startContent={<ArrowPathIcon className="h-3.5 w-3.5" />}
                isLoading={actionInProgress === 'retry-all'}
                onPress={handleRetryAll}
              >
                {ar.health.retryAll}
              </Button>
              <Button
                size="sm"
                variant="flat"
                color="danger"
                startContent={<TrashIcon className="h-3.5 w-3.5" />}
                isLoading={actionInProgress === 'flush-all'}
                onPress={() => setIsFlushModalOpen(true)}
              >
                {ar.health.flushAll}
              </Button>
            </div>
          )}
        </CardHeader>

        <CardBody className="p-0">
          {(data?.failed_jobs.recent.length ?? 0) === 0 ? (
            <div className="flex flex-col items-center justify-center p-12 text-center">
              <CheckCircleIcon className="h-6 w-6 text-success mb-2" />
              <p className="text-base font-semibold text-foreground">
                {ar.health.failedJobsEmpty}
              </p>
              <p className="text-sm text-default-400 mt-1 max-w-sm">
                كافة المهام المنفذة في طوابير العمل تمت معالجتها دون أخطاء.
              </p>
            </div>
          ) : (
            <DataTable
              columns={failedJobColumns}
              data={data?.failed_jobs.recent ?? []}
              keyExtractor={(job) => job.id}
            />
          )}
        </CardBody>
      </Card>

      {/* Flush Failed Jobs Confirmation Modal */}
      <ConfirmDialog
        isOpen={isFlushModalOpen}
        onClose={() => setIsFlushModalOpen(false)}
        onConfirm={async () => {
          setIsFlushModalOpen(false);
          await handleFlushAll();
        }}
        title="تأكيد حذف جميع المهام الفاشلة"
        message="هل أنت متأكد من رغبتك في حذف جميع المهام الفاشلة نهائياً؟ لا يمكن استرجاع هذه المهام أو إعادة تشغيلها بعد الحذف."
        variant="danger"
        confirmText="نعم، حذف الكل"
        cancelText={ar.common.cancel}
        isLoading={actionInProgress === 'flush-all'}
      />
    </PageContainer>
  );
};

export default HealthPage;
