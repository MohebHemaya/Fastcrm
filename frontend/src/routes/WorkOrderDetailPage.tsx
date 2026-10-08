import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  Button,
  Tabs,
  Tab,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  PaperAirplaneIcon,
  DocumentDuplicateIcon,
  XCircleIcon,
  UsersIcon,
  CheckCircleIcon,
  ShieldCheckIcon,
  UserPlusIcon,
  ArrowUpCircleIcon,
  UserMinusIcon,
  ArrowsUpDownIcon,
  SparklesIcon,
  ClockIcon,
  DocumentTextIcon,
  DocumentArrowDownIcon,
} from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { ar } from '@/i18n/ar';
import { workOrderService } from '@/services/workOrderService';
import exportService from '@/services/exportService';
import type {
  WorkOrder,
  WorkOrderRecipient,
  WorkOrderRecipientCounts,
  RecordOutcomeItem,
} from '@/types/workOrder';
import WorkOrderStatusBadge from '@/features/workOrders/components/WorkOrderStatusBadge';
import WorkOrderRecipientStatusBadge from '@/features/workOrders/components/WorkOrderRecipientStatusBadge';
import CancelOrderModal from '@/features/workOrders/components/CancelOrderModal';
import OpenSlotsBanner from '@/features/workOrders/components/OpenSlotsBanner';
import PromoteBackupModal from '@/features/workOrders/components/PromoteBackupModal';
import RemoveDriverModal from '@/features/workOrders/components/RemoveDriverModal';
import AddDriverModal from '@/features/workOrders/components/AddDriverModal';
import ResendOffersModal from '@/features/workOrders/components/ResendOffersModal';
import ChangeOrderStatusModal from '@/features/workOrders/components/ChangeOrderStatusModal';
import RecordOutcomesModal from '@/features/workOrders/components/RecordOutcomesModal';
import OrderTimelineTab from '@/features/workOrders/components/OrderTimelineTab';
import ExportModal from '@/components/exports/ExportModal';
import { useUiStore } from '@/lib/stores/useUiStore';

export const WorkOrderDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const orderId = Number(id);
  const addToast = useUiStore((state) => state.addToast);

  const [order, setOrder] = useState<WorkOrder | null>(null);
  const [recipientCounts, setRecipientCounts] = useState<WorkOrderRecipientCounts>({
    all: 0,
    confirmed: 0,
    backup: 0,
    declined: 0,
    sent: 0,
    too_late: 0,
    failed: 0,
    cancelled: 0,
  });

  const [isLoadingOrder, setIsLoadingOrder] = useState(true);
  const [activeTab, setActiveTab] = useState<string>('confirmed');
  const [recipients, setRecipients] = useState<WorkOrderRecipient[]>([]);
  const [isLoadingRecipients, setIsLoadingRecipients] = useState(false);
  const [recipientsPage, setRecipientsPage] = useState(1);
  const [recipientsTotal, setRecipientsTotal] = useState(0);

  // Cached lists for modals
  const [backupRecipients, setBackupRecipients] = useState<WorkOrderRecipient[]>([]);
  const [confirmedRecipients, setConfirmedRecipients] = useState<WorkOrderRecipient[]>([]);

  // Modals state
  const [isSending, setIsSending] = useState(false);
  const [isDuplicating, setIsDuplicating] = useState(false);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);
  const [retryingRecipientId, setRetryingRecipientId] = useState<number | null>(null);

  const [isPromoteModalOpen, setIsPromoteModalOpen] = useState(false);
  const [isPromoting, setIsPromoting] = useState(false);

  const [isRemoveModalOpen, setIsRemoveModalOpen] = useState(false);
  const [selectedRecipientToRemove, setSelectedRecipientToRemove] = useState<WorkOrderRecipient | null>(null);
  const [isRemoving, setIsRemoving] = useState(false);

  const [isAddDriverModalOpen, setIsAddDriverModalOpen] = useState(false);
  const [isAddingDriver, setIsAddingDriver] = useState(false);

  const [isResendModalOpen, setIsResendModalOpen] = useState(false);
  const [isResending, setIsResending] = useState(false);

  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
  const [isChangingStatus, setIsChangingStatus] = useState(false);

  const [isOutcomesModalOpen, setIsOutcomesModalOpen] = useState(false);
  const [isRecordingOutcomes, setIsRecordingOutcomes] = useState(false);

  const [isExportModalOpen, setIsExportModalOpen] = useState(false);
  const [activeExportId, setActiveExportId] = useState<number | null>(null);
  const [isExportingExcel, setIsExportingExcel] = useState(false);
  const [isDownloadingPdf, setIsDownloadingPdf] = useState(false);

  const handleDownloadPdf = async () => {
    if (!order) return;
    setIsDownloadingPdf(true);
    try {
      await exportService.downloadWorkOrderPdf(order.id, order.code);
      addToast({
        type: 'success',
        message: ar.exports.statusDone,
      });
    } catch (e: any) {
      console.error('PDF download failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setIsDownloadingPdf(false);
    }
  };

  const handleExportExcel = async () => {
    if (!order) return;
    setIsExportingExcel(true);
    try {
      const res = await exportService.requestExport({
        type: 'work_order',
        params: { work_order_id: order.id, order_id: order.id },
      });
      setActiveExportId(res.data.id);
      setIsExportModalOpen(true);
      addToast({
        type: 'info',
        message: ar.exports.exportStarted,
      });
    } catch (e: any) {
      console.error('Excel export failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setIsExportingExcel(false);
    }
  };

  // Fetch order data
  const fetchOrderData = useCallback(
    async (showLoadingSpinner = false) => {
      if (!orderId) return;
      if (showLoadingSpinner) setIsLoadingOrder(true);
      try {
        const res = await workOrderService.getWorkOrder(orderId);
        setOrder(res.data);
        if (res.meta?.recipient_counts) {
          setRecipientCounts(res.meta.recipient_counts);
        }
      } catch (err: any) {
        console.error('Failed to load work order', err);
        addToast({ message: err?.message || ar.errors.generalError, type: 'error' });
      } finally {
        if (showLoadingSpinner) setIsLoadingOrder(false);
      }
    },
    [orderId, addToast]
  );

  // Fetch recipients for active tab
  const fetchRecipients = useCallback(
    async (showLoadingSpinner = false) => {
      if (!orderId || activeTab === 'timeline') return;
      if (showLoadingSpinner) setIsLoadingRecipients(true);
      try {
        const statusParam = activeTab === 'all' ? undefined : activeTab;
        const res = await workOrderService.getRecipients(orderId, {
          status: statusParam,
          page: recipientsPage,
          per_page: 50,
        });
        setRecipients(res.data);
        setRecipientsTotal(res.meta.total);
      } catch (err: any) {
        console.error('Failed to load recipients', err);
      } finally {
        if (showLoadingSpinner) setIsLoadingRecipients(false);
      }
    },
    [orderId, activeTab, recipientsPage]
  );

  // Fetch backups and confirmed for modals
  const fetchModalData = useCallback(async () => {
    if (!orderId) return;
    try {
      const [backupsRes, confirmedRes] = await Promise.all([
        workOrderService.getRecipients(orderId, { status: 'backup', per_page: 50 }),
        workOrderService.getRecipients(orderId, { status: 'confirmed', per_page: 50 }),
      ]);
      setBackupRecipients(backupsRes.data || []);
      setConfirmedRecipients(confirmedRes.data || []);
    } catch (e) {
      console.error('Failed to load backups/confirmed recipients', e);
    }
  }, [orderId]);

  // Initial load
  useEffect(() => {
    fetchOrderData(true);
    fetchModalData();
  }, [fetchOrderData, fetchModalData]);

  // Recipient list load on tab or page change
  useEffect(() => {
    fetchRecipients(true);
  }, [fetchRecipients]);

  // Live polling every 10 seconds (paused when document is hidden)
  useEffect(() => {
    const interval = setInterval(() => {
      if (document.visibilityState === 'visible') {
        fetchOrderData(false);
        fetchRecipients(false);
        fetchModalData();
      }
    }, 10000);

    return () => clearInterval(interval);
  }, [fetchOrderData, fetchRecipients, fetchModalData]);

  // Handle Send Draft Order
  const handleSendOrder = async () => {
    if (!order || isSending) return;
    setIsSending(true);
    try {
      await workOrderService.sendOrder(order.id);
      addToast({ message: ar.workOrders.sendSuccess, type: 'success' });
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to send order', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsSending(false);
    }
  };

  // Handle Duplicate Order
  const handleDuplicate = async () => {
    if (!order) return;
    setIsDuplicating(true);
    try {
      const res = await workOrderService.duplicateOrder(order.id);
      addToast({ message: ar.workOrders.duplicateSuccess, type: 'success' });
      navigate(`/work-orders/${res.data.id}`);
    } catch (err: any) {
      console.error('Failed to duplicate order', err);
      addToast({ message: err?.message || ar.errors.generalError, type: 'error' });
    } finally {
      setIsDuplicating(false);
    }
  };

  // Handle Cancel Order
  const handleCancelOrder = async (reason: string) => {
    if (!order) return;
    setIsCancelling(true);
    try {
      await workOrderService.cancelOrder(order.id, reason);
      addToast({ message: ar.workOrders.cancelSuccess, type: 'success' });
      setIsCancelModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to cancel order', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsCancelling(false);
    }
  };

  // Handle Promote Backup
  const handlePromoteBackup = async (recipientId?: number) => {
    if (!order) return;
    setIsPromoting(true);
    try {
      await workOrderService.promoteBackup(order.id, recipientId);
      addToast({ message: 'تمت ترقية السائق الاحتياطي إلى مؤكد بنجاح.', type: 'success' });
      setIsPromoteModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to promote backup', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsPromoting(false);
    }
  };

  // Handle Remove Driver (confirmed or backup)
  const handleRemoveDriver = async (reason: string) => {
    if (!order || !selectedRecipientToRemove) return;
    setIsRemoving(true);
    try {
      if (selectedRecipientToRemove.status === 'confirmed') {
        await workOrderService.removeConfirmedDriver(order.id, selectedRecipientToRemove.id, reason);
      } else {
        await workOrderService.removeBackupDriver(order.id, selectedRecipientToRemove.id, reason);
      }
      addToast({ message: 'تم إلغاء تكليف السائق بنجاح.', type: 'success' });
      setIsRemoveModalOpen(false);
      setSelectedRecipientToRemove(null);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to remove driver', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsRemoving(false);
    }
  };

  // Handle Add Driver Manually
  const handleAddDriver = async (driverId: number, as: 'confirmed' | 'backup') => {
    if (!order) return;
    setIsAddingDriver(true);
    try {
      await workOrderService.addDriverManually(order.id, driverId, as);
      addToast({ message: 'تمت إضافة السائق بنجاح وإرسال الإشعار.', type: 'success' });
      setIsAddDriverModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to add driver manually', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsAddingDriver(false);
    }
  };

  // Handle Resend Offers
  const handleResendOffers = async () => {
    if (!order) return;
    setIsResending(true);
    try {
      const res = await workOrderService.resendOffers(order.id);
      addToast({
        message: `تمت إعادة إرسال العروض إلى ${res.data.resent_count} سائقين وإعادة فتح الطلب.`,
        type: 'success',
      });
      setIsResendModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to resend offers', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsResending(false);
    }
  };

  // Handle Change Status
  const handleChangeStatus = async (
    newStatus: 'in_progress' | 'completed' | 'cancelled',
    reason?: string
  ) => {
    if (!order) return;
    setIsChangingStatus(true);
    try {
      await workOrderService.changeStatus(order.id, newStatus, reason);
      addToast({ message: 'تم تحديث حالة أمر العمل بنجاح.', type: 'success' });
      setIsStatusModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to change status', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsChangingStatus(false);
    }
  };

  // Handle Record Outcomes
  const handleRecordOutcomes = async (outcomes: RecordOutcomeItem[]) => {
    if (!order) return;
    setIsRecordingOutcomes(true);
    try {
      await workOrderService.recordOutcomes(order.id, outcomes);
      addToast({ message: ar.workOrders.outcomes.savedSuccess, type: 'success' });
      setIsOutcomesModalOpen(false);
      await fetchOrderData(false);
      await fetchRecipients(true);
      await fetchModalData();
    } catch (err: any) {
      console.error('Failed to record outcomes', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsRecordingOutcomes(false);
    }
  };

  // Handle Retry Failed Recipient
  const handleRetryRecipient = async (recipientId: number) => {
    if (!order) return;
    setRetryingRecipientId(recipientId);
    try {
      await workOrderService.retryRecipient(order.id, recipientId);
      addToast({ message: ar.workOrders.recipients.retrySuccess, type: 'success' });
      await fetchOrderData(false);
      await fetchRecipients(false);
    } catch (err: any) {
      console.error('Failed to retry recipient', err);
      addToast({
        message: err?.response?.data?.message || err?.message || ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setRetryingRecipientId(null);
    }
  };

  if (isLoadingOrder && !order) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spinner size="lg" label={ar.app.loading} />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="p-6 text-center">
        <p className="text-default-500">{ar.errors.notFoundTitle}</p>
        <Button className="mt-4" onPress={() => navigate('/work-orders')}>
          {ar.workOrders.title}
        </Button>
      </div>
    );
  }

  const hasOpenSlots = Boolean(
    order.has_open_slots ||
      (order.status === 'filled' && order.confirmed_count < order.trips_needed)
  );
  const openSlotsCount = order.open_slots_count ?? Math.max(0, order.trips_needed - order.confirmed_count);

  const canAddConfirmed = order.confirmed_count < order.trips_needed;
  const canAddBackup = order.backup_count < order.backup_needed;

  const columns: ColumnDef<WorkOrderRecipient>[] = [
    {
      id: 'sequence',
      header: ar.workOrders.recipients.sequenceNumber,
      render: (row) =>
        row.sequence_number ? (
          <span className="font-mono font-bold text-primary">#{row.sequence_number}</span>
        ) : (
          <span className="text-default-300">—</span>
        ),
    },
    {
      id: 'driver_name',
      header: ar.workOrders.recipients.driverName,
      isPrimary: true,
      render: (row) => (
        <span className="font-medium text-foreground">{row.driver_name}</span>
      ),
    },
    {
      id: 'driver_phone',
      header: ar.workOrders.recipients.phone,
      render: (row) => (
        <span dir="ltr" className="text-default-600 font-mono text-xs">
          {row.driver_phone}
        </span>
      ),
    },
    {
      id: 'sent_at',
      header: ar.workOrders.recipients.sentAt,
      render: (row) =>
        row.sent_at ? (
          <span dir="ltr" className="text-xs text-default-500">
            {new Date(row.sent_at).toLocaleTimeString('ar-EG', {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </span>
        ) : (
          <span className="text-default-300">—</span>
        ),
    },
    {
      id: 'responded_at',
      header: ar.workOrders.recipients.respondedAt,
      render: (row) =>
        row.responded_at ? (
          <span dir="ltr" className="text-xs text-default-500">
            {new Date(row.responded_at).toLocaleTimeString('ar-EG', {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </span>
        ) : (
          <span className="text-default-300">—</span>
        ),
    },
    {
      id: 'status',
      header: ar.workOrders.recipients.status,
      render: (row) => (
        <div className="flex flex-col gap-1 items-start">
          <WorkOrderRecipientStatusBadge status={row.status} />
          {row.outcome && (
            <Chip
              size="sm"
              variant="flat"
              color={
                row.outcome === 'attended'
                  ? 'success'
                  : row.outcome === 'late'
                  ? 'warning'
                  : 'danger'
              }
              className="text-[11px]"
            >
              {row.outcome_label || row.outcome} {row.rating ? `⭐ ${row.rating}` : ''}
            </Chip>
          )}
        </div>
      ),
    },
    {
      id: 'actions',
      header: ar.workOrders.recipients.actions,
      className: 'text-end',
      headerClassName: 'text-end',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          {row.status === 'failed' && (
            <Button
              size="sm"
              color="primary"
              variant="flat"
              isLoading={retryingRecipientId === row.id}
              onPress={() => handleRetryRecipient(row.id)}
            >
              {ar.workOrders.recipients.retry}
            </Button>
          )}

          {row.status === 'confirmed' &&
            order.status !== 'completed' &&
            order.status !== 'cancelled' && (
              <Button
                size="sm"
                color="danger"
                variant="light"
                startContent={<UserMinusIcon className="w-3.5 h-3.5" />}
                onPress={() => {
                  setSelectedRecipientToRemove(row);
                  setIsRemoveModalOpen(true);
                }}
              >
                إلغاء تكليف
              </Button>
            )}

          {row.status === 'backup' &&
            order.status !== 'completed' &&
            order.status !== 'cancelled' && (
              <>
                <Button
                  size="sm"
                  color="warning"
                  variant="flat"
                  startContent={<ArrowUpCircleIcon className="w-3.5 h-3.5" />}
                  onPress={() => {
                    handlePromoteBackup(row.id);
                  }}
                  isLoading={isPromoting}
                  isDisabled={order.confirmed_count >= order.trips_needed}
                >
                  ترقية
                </Button>
                <Button
                  size="sm"
                  color="danger"
                  variant="light"
                  onPress={() => {
                    setSelectedRecipientToRemove(row);
                    setIsRemoveModalOpen(true);
                  }}
                >
                  حذف
                </Button>
              </>
            )}
        </div>
      ),
    },
  ];

  return (
    <PageContainer className="space-y-6">
      {/* Page Header */}
      <PageHeader
        title={`${order.code} — ${order.from_location} ← ${order.to_location}`}
        description={`تاريخ الموعد: ${new Date(order.scheduled_at).toLocaleString('ar-EG')}`}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.workOrders.title, href: '/work-orders' },
          { label: order.code },
        ]}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {order.status === 'draft' && (
              <Button
                color="primary"
                className="min-h-[44px]"
                startContent={<PaperAirplaneIcon className="w-4 h-4 rtl:-scale-x-100" />}
                onPress={handleSendOrder}
                isLoading={isSending}
                isDisabled={isSending}
              >
                {ar.workOrders.sendOrder}
              </Button>
            )}

            {order.status !== 'cancelled' && (
              <Button
                color="primary"
                variant="flat"
                className="min-h-[44px]"
                startContent={<UserPlusIcon className="w-4 h-4" />}
                onPress={() => setIsAddDriverModalOpen(true)}
                onClick={() => setIsAddDriverModalOpen(true)}
              >
                {ar.workOrders.addDriverModal.title}
              </Button>
            )}

            {['filled', 'in_progress'].includes(order.status) && (
              <Button
                color="secondary"
                variant="flat"
                className="min-h-[44px]"
                startContent={<ArrowsUpDownIcon className="w-4 h-4" />}
                onPress={() => setIsStatusModalOpen(true)}
                onClick={() => setIsStatusModalOpen(true)}
              >
                {ar.workOrders.statusModal.title}
              </Button>
            )}

            {order.status === 'completed' && (
              <Button
                color="success"
                variant="flat"
                className="min-h-[44px]"
                startContent={<SparklesIcon className="w-4 h-4" />}
                onPress={() => setIsOutcomesModalOpen(true)}
                onClick={() => setIsOutcomesModalOpen(true)}
              >
                {ar.workOrders.outcomes.button}
              </Button>
            )}

            <Button
              variant="flat"
              className="min-h-[44px]"
              startContent={<DocumentDuplicateIcon className="w-4 h-4" />}
              onPress={handleDuplicate}
              onClick={handleDuplicate}
              isLoading={isDuplicating}
            >
              {ar.workOrders.duplicateOrder}
            </Button>

            <Button
              color="secondary"
              variant="flat"
              className="min-h-[44px]"
              startContent={<DocumentTextIcon className="w-4 h-4" />}
              onPress={handleDownloadPdf}
              isLoading={isDownloadingPdf}
            >
              {ar.exports.exportOrderPdf}
            </Button>

            <Button
              color="default"
              variant="flat"
              className="min-h-[44px]"
              startContent={<DocumentArrowDownIcon className="w-4 h-4" />}
              onPress={handleExportExcel}
              isLoading={isExportingExcel}
            >
              {ar.exports.exportOrderExcel}
            </Button>

            {order.status !== 'cancelled' && order.status !== 'completed' && (
              <Button
                color="danger"
                variant="flat"
                className="min-h-[44px]"
                startContent={<XCircleIcon className="w-4 h-4" />}
                onPress={() => setIsCancelModalOpen(true)}
                onClick={() => setIsCancelModalOpen(true)}
              >
                {ar.workOrders.cancelOrder}
              </Button>
            )}
          </div>
        }
      />

      {/* Open Slots Warning Banner */}
      {hasOpenSlots && (
        <OpenSlotsBanner
          openSlotsCount={openSlotsCount}
          onPromote={() => setIsPromoteModalOpen(true)}
          onAddDriver={() => setIsAddDriverModalOpen(true)}
          onResendOffers={() => setIsResendModalOpen(true)}
        />
      )}

      {/* Stats Cards Overview */}
      <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        {/* Trips Needed Card */}
        <Card className="shadow-sm">
          <CardBody className="p-4 flex items-center justify-between">
            <div>
              <span className="text-xs text-default-500 font-medium">
                {ar.workOrders.stats.tripsNeeded}
              </span>
              <p className="text-2xl font-bold text-foreground mt-1">
                {order.trips_needed}
              </p>
            </div>
            <UsersIcon className="w-7 h-7 text-default-300" />
          </CardBody>
        </Card>

        {/* Confirmed Count Card */}
        <Card className="shadow-sm border-t-2 border-t-success">
          <CardBody className="p-4 flex items-center justify-between">
            <div>
              <span className="text-xs text-default-500 font-medium">
                {ar.workOrders.stats.confirmed}
              </span>
              <p className="text-2xl font-bold text-success mt-1">
                {order.confirmed_count} / {order.trips_needed}
              </p>
            </div>
            <CheckCircleIcon className="w-7 h-7 text-success-300" />
          </CardBody>
        </Card>

        {/* Backup Count Card */}
        <Card className="shadow-sm border-t-2 border-t-warning">
          <CardBody className="p-4 flex items-center justify-between">
            <div>
              <span className="text-xs text-default-500 font-medium">
                {ar.workOrders.stats.backup}
              </span>
              <p className="text-2xl font-bold text-warning-600 mt-1">
                {order.backup_count} / {order.backup_needed}
              </p>
            </div>
            <ShieldCheckIcon className="w-7 h-7 text-warning-300" />
          </CardBody>
        </Card>

        {/* Sent Offers Card */}
        <Card className="shadow-sm">
          <CardBody className="p-4 flex items-center justify-between">
            <div>
              <span className="text-xs text-default-500 font-medium">
                {ar.workOrders.stats.sentOffers}
              </span>
              <p className="text-2xl font-bold text-primary mt-1">
                {order.sent_count}
              </p>
            </div>
            <PaperAirplaneIcon className="w-7 h-7 text-primary-300 rtl:-scale-x-100" />
          </CardBody>
        </Card>

        {/* Status Card */}
        <Card className="shadow-sm">
          <CardBody className="p-4 flex flex-col justify-center">
            <span className="text-xs text-default-500 font-medium mb-1.5">
              {ar.common.status}
            </span>
            <div>
              <WorkOrderStatusBadge status={order.status} size="md" />
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Tabs for Recipients & Timeline */}
      <Card className="shadow-sm">
        <CardBody className="p-4 space-y-4">
          <div className="overflow-x-auto max-w-full pb-1">
            <Tabs
              selectedKey={activeTab}
              onSelectionChange={(key) => {
                setActiveTab(String(key));
                setRecipientsPage(1);
              }}
              variant="underlined"
              color="primary"
              disableAnimation
            >
              <Tab
                key="confirmed"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.confirmed}</span>
                    <Chip size="sm" variant="flat" color="success">
                      {recipientCounts.confirmed}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="backup"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.backup}</span>
                    <Chip size="sm" variant="flat" color="warning">
                      {recipientCounts.backup}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="declined"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.declined}</span>
                    <Chip size="sm" variant="flat" color="danger">
                      {recipientCounts.declined}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="sent"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.sent}</span>
                    <Chip size="sm" variant="flat" color="primary">
                      {recipientCounts.sent}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="too_late"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.too_late}</span>
                    <Chip size="sm" variant="flat" color="default">
                      {recipientCounts.too_late}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="all"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.workOrders.tabs.all}</span>
                    <Chip size="sm" variant="flat">
                      {recipientCounts.all}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="timeline"
                title={
                  <div className="flex items-center gap-2">
                    <ClockIcon className="w-3.5 h-3.5" />
                    <span>{ar.workOrders.timeline.tabTitle}</span>
                  </div>
                }
              />
            </Tabs>
          </div>

          {/* Tab Content */}
          {activeTab === 'timeline' ? (
            <OrderTimelineTab orderId={order.id} />
          ) : (
            <DataTable
              columns={columns}
              data={recipients}
              keyExtractor={(row) => row.id}
              isLoading={isLoadingRecipients}
              currentPage={recipientsPage}
              lastPage={Math.max(1, Math.ceil(recipientsTotal / 50))}
              total={recipientsTotal}
              onPageChange={setRecipientsPage}
              emptyTitle={ar.workOrders.recipients.empty}
              emptyIcon={<UsersIcon className="w-10 h-10 text-default-300" />}
            />
          )}
        </CardBody>
      </Card>

      {/* Promote Backup Modal */}
      <PromoteBackupModal
        isOpen={isPromoteModalOpen}
        onClose={() => setIsPromoteModalOpen(false)}
        backups={backupRecipients}
        onConfirm={handlePromoteBackup}
        isLoading={isPromoting}
      />

      {/* Remove Driver Modal */}
      <RemoveDriverModal
        isOpen={isRemoveModalOpen}
        onClose={() => {
          setIsRemoveModalOpen(false);
          setSelectedRecipientToRemove(null);
        }}
        recipient={selectedRecipientToRemove}
        isConfirmed={selectedRecipientToRemove?.status === 'confirmed'}
        onConfirm={handleRemoveDriver}
        isLoading={isRemoving}
      />

      {/* Add Driver Manually Modal */}
      <AddDriverModal
        isOpen={isAddDriverModalOpen}
        onClose={() => setIsAddDriverModalOpen(false)}
        canAddConfirmed={canAddConfirmed}
        canAddBackup={canAddBackup}
        onConfirm={handleAddDriver}
        isLoading={isAddingDriver}
      />

      {/* Resend Offers Modal */}
      <ResendOffersModal
        isOpen={isResendModalOpen}
        onClose={() => setIsResendModalOpen(false)}
        onConfirm={handleResendOffers}
        isLoading={isResending}
      />

      {/* Change Status Modal */}
      <ChangeOrderStatusModal
        isOpen={isStatusModalOpen}
        onClose={() => setIsStatusModalOpen(false)}
        currentStatus={order.status}
        onConfirm={handleChangeStatus}
        isLoading={isChangingStatus}
      />

      {/* Record Outcomes Modal */}
      <RecordOutcomesModal
        isOpen={isOutcomesModalOpen}
        onClose={() => setIsOutcomesModalOpen(false)}
        confirmedRecipients={confirmedRecipients}
        onConfirm={handleRecordOutcomes}
        isLoading={isRecordingOutcomes}
      />

      {/* Cancel Order Modal */}
      <CancelOrderModal
        isOpen={isCancelModalOpen}
        onClose={() => setIsCancelModalOpen(false)}
        onConfirm={handleCancelOrder}
        isLoading={isCancelling}
      />

      {/* Export Modal */}
      <ExportModal
        isOpen={isExportModalOpen}
        onClose={() => setIsExportModalOpen(false)}
        activeExportId={activeExportId}
      />
    </PageContainer>
  );
};

export default WorkOrderDetailPage;
