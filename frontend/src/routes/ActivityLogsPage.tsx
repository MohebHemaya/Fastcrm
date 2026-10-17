import React, { useState, useEffect, useCallback } from 'react';
import { Input, Select, SelectItem, Button, Chip } from '@heroui/react';
import {
  FunnelIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  KeyIcon,
  ArrowRightOnRectangleIcon,
  ArrowLeftOnRectangleIcon,
} from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import {
  activityLogService,
  type ActivityLogItem,
  type ActivityLogsFilterParams,
} from '@/services/activityLogService';
import { useUiStore } from '@/lib/stores/useUiStore';
import { ar } from '@/i18n/ar';

export const ActivityLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<ActivityLogItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  // Filter states
  const [actionFilter, setActionFilter] = useState<string>('');
  const [dateFromFilter, setDateFromFilter] = useState<string>('');
  const [dateToFilter, setDateToFilter] = useState<string>('');
  const [showMobileFilters, setShowMobileFilters] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  const fetchLogs = useCallback(
    async (page = 1) => {
      setIsLoading(true);
      try {
        const params: ActivityLogsFilterParams = {
          page,
          per_page: 15,
        };
        if (actionFilter) params.action = actionFilter;
        if (dateFromFilter) params.date_from = dateFromFilter;
        if (dateToFilter) params.date_to = dateToFilter;

        const res = await activityLogService.getActivityLogs(params);
        setLogs(res.data);
        setCurrentPage(res.meta.current_page);
        setLastPage(res.meta.last_page);
        setTotal(res.meta.total);
      } catch (err: any) {
        addToast({
          type: 'error',
          message: err?.response?.data?.message || ar.errors.generalError,
        });
      } finally {
        setIsLoading(false);
      }
    },
    [actionFilter, dateFromFilter, dateToFilter, addToast]
  );

  useEffect(() => {
    fetchLogs(currentPage);
  }, [fetchLogs, currentPage]);

  const handleApplyFilters = () => {
    setCurrentPage(1);
    fetchLogs(1);
  };

  const handleResetFilters = () => {
    setActionFilter('');
    setDateFromFilter('');
    setDateToFilter('');
    setCurrentPage(1);
  };

  const getActionBadge = (action: string) => {
    switch (action) {
      case 'login':
        return (
          <Chip
            size="sm"
            color="success"
            variant="flat"
            startContent={<ArrowRightOnRectangleIcon className="h-3.5 w-3.5 rtl:rotate-180" />}
          >
            {ar.activity.actions.login}
          </Chip>
        );
      case 'logout':
        return (
          <Chip
            size="sm"
            color="default"
            variant="flat"
            startContent={<ArrowLeftOnRectangleIcon className="h-3.5 w-3.5 rtl:rotate-180" />}
          >
            {ar.activity.actions.logout}
          </Chip>
        );
      case 'failed_login':
        return (
          <Chip
            size="sm"
            color="danger"
            variant="flat"
            startContent={<ExclamationTriangleIcon className="h-3.5 w-3.5" />}
          >
            {ar.activity.actions.failed_login}
          </Chip>
        );
      case 'password_change':
        return (
          <Chip
            size="sm"
            color="warning"
            variant="flat"
            startContent={<KeyIcon className="h-3.5 w-3.5" />}
          >
            {ar.activity.actions.password_change}
          </Chip>
        );
      default:
        return (
          <Chip size="sm" variant="flat">
            {action}
          </Chip>
        );
    }
  };

  const formatDateTime = (dateString?: string) => {
    if (!dateString) return '-';
    try {
      const date = new Date(dateString);
      return new Intl.DateTimeFormat('ar-EG', {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(date);
    } catch {
      return dateString;
    }
  };

  const formatProperties = (props: Record<string, any> | null) => {
    if (!props || Object.keys(props).length === 0) return '-';
    return (
      <div className="flex flex-wrap gap-1 max-w-xs">
        {Object.entries(props).map(([k, v]) => (
          <span
            key={k}
            className="rounded bg-default-100 px-1.5 py-0.5 text-[11px] text-default-600"
          >
            <strong>{k}:</strong> {typeof v === 'object' ? JSON.stringify(v) : String(v)}
          </span>
        ))}
      </div>
    );
  };

  const columns: ColumnDef<ActivityLogItem>[] = [
    {
      id: 'action',
      header: ar.activity.action,
      isPrimary: true,
      render: (row) => getActionBadge(row.action),
    },
    {
      id: 'user',
      header: ar.activity.user,
      render: (row) => (
        <div className="flex flex-col text-start">
          <span className="font-semibold text-default-900">
            {row.user?.name || '-'}
          </span>
          {row.user?.email && (
            <span className="text-xs text-default-400">{row.user.email}</span>
          )}
        </div>
      ),
    },
    {
      id: 'properties',
      header: ar.activity.details,
      render: (row) => formatProperties(row.properties),
    },
    {
      id: 'ip',
      header: ar.activity.ip,
      render: (row) => (
        <span className="font-mono text-xs text-default-600">
          {row.ip || '-'}
        </span>
      ),
    },
    {
      id: 'created_at',
      header: ar.activity.date,
      render: (row) => (
        <span className="text-xs text-default-500">
          {formatDateTime(row.created_at)}
        </span>
      ),
    },
  ];

  return (
    <PageContainer>
      <PageHeader
        title={ar.activity.title}
        description={ar.activity.subtitle}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.activity.title },
        ]}
      />

      <DataTable
        columns={columns}
        data={logs}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        emptyTitle={ar.activity.noLogs}
        currentPage={currentPage}
        lastPage={lastPage}
        total={total}
        onPageChange={(page) => setCurrentPage(page)}
        topContent={
          <div className="flex flex-col gap-3 rounded-xl border border-default-200 bg-content1 p-4 shadow-none">
            <div className="flex items-center justify-between gap-2 md:hidden">
              <span className="font-semibold text-sm text-default-700">{ar.activity.filter}</span>
              <Button
                variant="flat"
                size="sm"
                color={actionFilter || dateFromFilter || dateToFilter ? 'primary' : 'default'}
                className="min-h-[44px] min-w-[44px]"
                onPress={() => setShowMobileFilters(!showMobileFilters)}
                startContent={<FunnelIcon className="h-4 w-4" />}
              >
                تصفية
                {(actionFilter || dateFromFilter || dateToFilter) && (
                  <Chip size="sm" color="primary" variant="solid" className="h-4 text-[10px] px-1 ms-1">
                    {(actionFilter ? 1 : 0) + (dateFromFilter ? 1 : 0) + (dateToFilter ? 1 : 0)}
                  </Chip>
                )}
              </Button>
            </div>
            <div
              className={`${
                showMobileFilters ? 'grid' : 'hidden'
              } md:grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 pt-2 md:pt-0 border-t md:border-t-0 border-default-100`}
            >
              <Select
                label={ar.activity.action}
                labelPlacement="outside"
                placeholder={ar.activity.allActions}
                selectedKeys={actionFilter ? [actionFilter] : []}
                onChange={(e) => setActionFilter(e.target.value)}
                size="sm"
                variant="bordered"
              >
                <SelectItem key="" textValue={ar.activity.allActions}>
                  {ar.activity.allActions}
                </SelectItem>
                <SelectItem key="login" textValue={ar.activity.actions.login}>
                  {ar.activity.actions.login}
                </SelectItem>
                <SelectItem key="logout" textValue={ar.activity.actions.logout}>
                  {ar.activity.actions.logout}
                </SelectItem>
                <SelectItem key="failed_login" textValue={ar.activity.actions.failed_login}>
                  {ar.activity.actions.failed_login}
                </SelectItem>
                <SelectItem key="password_change" textValue={ar.activity.actions.password_change}>
                  {ar.activity.actions.password_change}
                </SelectItem>
              </Select>

              <Input
                type="date"
                label={ar.activity.dateFrom}
                labelPlacement="outside"
                value={dateFromFilter}
                onChange={(e) => setDateFromFilter(e.target.value)}
                size="sm"
                variant="bordered"
              />

              <Input
                type="date"
                label={ar.activity.dateTo}
                labelPlacement="outside"
                value={dateToFilter}
                onChange={(e) => setDateToFilter(e.target.value)}
                size="sm"
                variant="bordered"
              />

              <div className="flex items-end gap-2 pt-2 sm:pt-0">
                <Button
                  color="primary"
                  size="md"
                  className="flex-1 min-h-[44px]"
                  startContent={<FunnelIcon className="h-4 w-4" />}
                  onPress={handleApplyFilters}
                >
                  {ar.activity.filter}
                </Button>
                <Button
                  variant="flat"
                  color="default"
                  size="md"
                  isIconOnly
                  className="min-h-[44px] min-w-[44px]"
                  aria-label={ar.activity.resetFilters}
                  onPress={handleResetFilters}
                >
                  <ArrowPathIcon className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>
        }
      />
    </PageContainer>
  );
};

export default ActivityLogsPage;
