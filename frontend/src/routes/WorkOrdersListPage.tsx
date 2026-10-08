import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Card,
  CardBody,
  Chip,
} from '@heroui/react';
import {
  PlusIcon,
  ArrowPathIcon,
  EyeIcon,
  DocumentDuplicateIcon,
  CalendarIcon,
  MapPinIcon,
  BriefcaseIcon,
  FunnelIcon,
} from '@/components/icons';
import PageContainer from '@/components/layout/PageContainer';
import { SearchInput } from '@/components/form';
import PageHeader from '@/components/ui/PageHeader';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { ar } from '@/i18n/ar';
import { workOrderService } from '@/services/workOrderService';
import type { WorkOrder } from '@/types/workOrder';
import WorkOrderStatusBadge from '@/features/workOrders/components/WorkOrderStatusBadge';
import { useUiStore } from '@/lib/stores/useUiStore';

export const WorkOrdersListPage: React.FC = () => {
  const navigate = useNavigate();
  const addToast = useUiStore((state) => state.addToast);

  const [orders, setOrders] = useState<WorkOrder[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(15);

  // Filters state
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [showMobileFilters, setShowMobileFilters] = useState(false);
  const [duplicatingId, setDuplicatingId] = useState<number | null>(null);

  // Debounce search input
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(search);
      setCurrentPage(1);
    }, 400);
    return () => clearTimeout(handler);
  }, [search]);

  // Fetch work orders
  const fetchOrders = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await workOrderService.getWorkOrders({
        page: currentPage,
        per_page: perPage,
        search: debouncedSearch || undefined,
        status: selectedStatus || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      });

      setOrders(response.data);
      setCurrentPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
      setPerPage(response.meta.per_page);
    } catch (err: any) {
      console.error('Failed to load work orders', err);
      addToast({ message: err?.message || ar.errors.generalError, type: 'error' });
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, perPage, debouncedSearch, selectedStatus, dateFrom, dateTo, addToast]);

  useEffect(() => {
    fetchOrders();
  }, [fetchOrders]);

  const handleResetFilters = () => {
    setSearch('');
    setDebouncedSearch('');
    setSelectedStatus('');
    setDateFrom('');
    setDateTo('');
    setCurrentPage(1);
  };

  const handleDuplicate = async (orderId: number, e?: any) => {
    e?.stopPropagation?.();
    setDuplicatingId(orderId);
    try {
      const res = await workOrderService.duplicateOrder(orderId);
      addToast({ message: ar.workOrders.duplicateSuccess, type: 'success' });
      navigate(`/work-orders/${res.data.id}`);
    } catch (err: any) {
      console.error('Failed to duplicate order', err);
      addToast({ message: err?.message || ar.errors.generalError, type: 'error' });
    } finally {
      setDuplicatingId(null);
    }
  };

  const statusOptions: Array<{ key: string; label: string }> = [
    { key: '', label: ar.drivers.allStatuses },
    { key: 'draft', label: ar.workOrders.status.draft },
    { key: 'sending', label: ar.workOrders.status.sending },
    { key: 'filling', label: ar.workOrders.status.filling },
    { key: 'filled', label: ar.workOrders.status.filled },
    { key: 'in_progress', label: ar.workOrders.status.in_progress },
    { key: 'completed', label: ar.workOrders.status.completed },
    { key: 'cancelled', label: ar.workOrders.status.cancelled },
  ];

  const columns: ColumnDef<WorkOrder>[] = [
    {
      id: 'code',
      header: ar.workOrders.fields.code,
      isPrimary: true,
      render: (row) => (
        <span className="font-mono font-semibold text-primary">
          {row.code}
        </span>
      ),
    },
    {
      id: 'route',
      header: 'خط السير',
      render: (row) => (
        <div className="flex flex-col text-sm">
          <div className="flex items-center gap-1.5 font-medium text-foreground">
            <MapPinIcon className="w-3.5 h-3.5 text-default-400 shrink-0" />
            <span>{row.from_location}</span>
            <span className="text-default-400">←</span>
            <span>{row.to_location}</span>
          </div>
          {row.cargo_type && (
            <span className="text-xs text-default-500 mt-0.5">
              الحمولة: {row.cargo_type}
            </span>
          )}
        </div>
      ),
    },
    {
      id: 'scheduled_at',
      header: ar.workOrders.fields.scheduledAt,
      render: (row) => {
        const dateObj = new Date(row.scheduled_at);
        return (
          <div className="flex items-center gap-1.5 text-xs text-default-600">
            <CalendarIcon className="w-3.5 h-3.5 text-default-400" />
            <span dir="ltr">
              {dateObj.toLocaleDateString('ar-EG', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
              })}
            </span>
          </div>
        );
      },
    },
    {
      id: 'capacity',
      header: 'الرحلات / التأكيدات',
      render: (row) => {
        return (
          <div className="flex flex-col gap-1 text-xs">
            <div className="flex items-center gap-2">
              <span className="text-default-600">المؤكد:</span>
              <span className="font-semibold text-success">
                {row.confirmed_count} / {row.trips_needed}
              </span>
            </div>
            {row.backup_needed > 0 && (
              <div className="flex items-center gap-2 text-default-500">
                <span>الاحتياطي:</span>
                <span>
                  {row.backup_count} / {row.backup_needed}
                </span>
              </div>
            )}
          </div>
        );
      },
    },
    {
      id: 'pay',
      header: ar.workOrders.fields.payAmount,
      render: (row) => (
        <span className="text-xs font-semibold text-default-700">
          {row.pay_amount ? `${Number(row.pay_amount).toLocaleString('ar-EG')} ج.م` : '—'}
        </span>
      ),
    },
    {
      id: 'status',
      header: ar.common.status,
      render: (row) => <WorkOrderStatusBadge status={row.status} />,
    },
    {
      id: 'actions',
      header: ar.common.actions,
      className: 'text-end',
      headerClassName: 'text-end',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Button
            size="sm"
            variant="light"
            isIconOnly
            className="min-w-[40px] min-h-[40px]"
            title={ar.workOrders.orderDetails}
            aria-label={ar.workOrders.orderDetails}
            onPress={() => navigate(`/work-orders/${row.id}`)}
          >
            <EyeIcon className="w-4 h-4 text-default-600" />
          </Button>
          <Button
            size="sm"
            variant="light"
            isIconOnly
            className="min-w-[40px] min-h-[40px]"
            title={ar.workOrders.duplicateOrder}
            aria-label={ar.workOrders.duplicateOrder}
            isLoading={duplicatingId === row.id}
            onPress={(e: any) => handleDuplicate(row.id, e)}
          >
            <DocumentDuplicateIcon className="w-4 h-4 text-default-600" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PageContainer className="space-y-6">
      {/* Header */}
      <PageHeader
        title={ar.workOrders.title}
        description={ar.workOrders.subtitle}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.workOrders.title },
        ]}
        actions={
          <Button
            color="primary"
            className="min-h-[44px]"
            startContent={<PlusIcon className="w-4 h-4" />}
            onPress={() => navigate('/work-orders/new')}
          >
            {ar.workOrders.createOrder}
          </Button>
        }
      />

      {/* Filters Card */}
      <Card className="shadow-sm">
        <CardBody className="p-4 space-y-3">
          <div className="flex items-center gap-2">
            <div className="flex-1 min-w-0">
              {/* Search Input */}
              <SearchInput
                placeholder={ar.workOrders.fields.searchPlaceholder}
                value={search}
                onSearch={(val) => {
                  setSearch(val);
                  setDebouncedSearch(val);
                  setCurrentPage(1);
                }}
                size="sm"
                variant="bordered"
                className="w-full"
                aria-label={ar.common.search}
              />
            </div>
            <Button
              variant="flat"
              size="sm"
              color={selectedStatus || dateFrom || dateTo ? 'primary' : 'default'}
              className="md:hidden min-h-[44px] min-w-[44px] shrink-0"
              onPress={() => setShowMobileFilters(!showMobileFilters)}
              startContent={<FunnelIcon className="h-4 w-4" />}
            >
              تصفية
              {(selectedStatus || dateFrom || dateTo) && (
                <Chip size="sm" color="primary" variant="solid" className="h-4 text-[10px] px-1 ms-1">
                  {(selectedStatus ? 1 : 0) + (dateFrom ? 1 : 0) + (dateTo ? 1 : 0)}
                </Chip>
              )}
            </Button>
          </div>

          <div
            className={`${
              showMobileFilters ? 'grid' : 'hidden'
            } md:grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 md:pt-0 border-t md:border-t-0 border-default-100`}
          >
            {/* Status Select */}
            <Select
              placeholder={ar.common.status}
              selectedKeys={selectedStatus ? [selectedStatus] : []}
              onChange={(e) => {
                setSelectedStatus(e.target.value);
                setCurrentPage(1);
              }}
              size="sm"
              variant="bordered"
              aria-label={ar.common.status}
            >
              {statusOptions.map((opt) => (
                <SelectItem key={opt.key} textValue={opt.label}>
                  {opt.label}
                </SelectItem>
              ))}
            </Select>

            {/* Date From */}
            <Input
              type="date"
              placeholder="من تاريخ"
              variant="bordered"
              value={dateFrom}
              onChange={(e) => {
                setDateFrom(e.target.value);
                setCurrentPage(1);
              }}
              size="sm"
              aria-label="من تاريخ"
            />

            {/* Date To */}
            <Input
              type="date"
              placeholder="إلى تاريخ"
              variant="bordered"
              value={dateTo}
              onChange={(e) => {
                setDateTo(e.target.value);
                setCurrentPage(1);
              }}
              size="sm"
              aria-label="إلى تاريخ"
            />

            {/* Reset Button */}
            <div className="flex items-center">
              <Button
                variant="flat"
                startContent={<ArrowPathIcon className="w-4 h-4" />}
                onPress={handleResetFilters}
                size="sm"
                className="w-full min-h-[44px]"
              >
                {ar.activity.resetFilters}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Orders Table */}
      <DataTable
        columns={columns}
        data={orders}
        keyExtractor={(row) => row.id}
        isLoading={isLoading}
        currentPage={currentPage}
        lastPage={lastPage}
        total={total}
        onPageChange={setCurrentPage}
        emptyTitle={ar.workOrders.emptyList}
        emptyDescription={ar.drivers.noDriversFound}
        emptyIcon={<BriefcaseIcon className="w-12 h-12 text-default-300" />}
      />
    </PageContainer>
  );
};

export default WorkOrdersListPage;
