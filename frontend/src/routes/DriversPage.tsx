import React, { useState, useEffect, useCallback, useTransition } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Select,
  SelectItem,
  Chip,
} from '@heroui/react';
import {
  UserPlusIcon,
  DocumentArrowDownIcon,
  ArrowPathIcon,
  EyeIcon,
  UsersIcon,
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  DocumentTextIcon,
  FunnelIcon,
} from '@/components/icons';
import PageContainer from '@/components/layout/PageContainer';
import { SearchInput } from '@/components/form';
import PageHeader from '@/components/ui/PageHeader';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { ar } from '@/i18n/ar';
import driverService from '@/services/driverService';
import exportService from '@/services/exportService';
import type { Driver, WhatsappGroup, DriverStatusValue } from '@/types/driver';
import DriverStatusBadge from '@/features/drivers/components/DriverStatusBadge';
import AddDriverModal from '@/features/drivers/components/AddDriverModal';
import DriverDetailDrawer from '@/features/drivers/components/DriverDetailDrawer';
import BulkInviteModal from '@/features/whatsapp/components/BulkInviteModal';
import DriverChatModal from '@/features/whatsapp/components/DriverChatModal';
import ExportModal from '@/components/exports/ExportModal';
import { useUiStore } from '@/lib/stores/useUiStore';

export const DriversPage: React.FC = () => {
  const navigate = useNavigate();
  const [, startTransition] = useTransition();

  const [drivers, setDrivers] = useState<Driver[]>([]);
  const [groups, setGroups] = useState<WhatsappGroup[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  // Pagination & Sorting state
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(15);
  const [sortBy, setSortBy] = useState<string>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  // Filter state
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [selectedGroup, setSelectedGroup] = useState<string>('');
  const [showMobileFilters, setShowMobileFilters] = useState(false);

  // Modals state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [selectedDriverId, setSelectedDriverId] = useState<number | null>(null);
  const [isDetailDrawerOpen, setIsDetailDrawerOpen] = useState(false);
  const [isBulkInviteOpen, setIsBulkInviteOpen] = useState(false);
  const [chatDriver, setChatDriver] = useState<Driver | null>(null);
  const [isExportModalOpen, setIsExportModalOpen] = useState(false);
  const [activeExportId, setActiveExportId] = useState<number | null>(null);
  const [isExporting, setIsExporting] = useState(false);
  const [downloadingPdfId, setDownloadingPdfId] = useState<number | null>(null);
  const addToast = useUiStore((state) => state.addToast);

  const handleExportDrivers = async () => {
    setIsExporting(true);
    try {
      const params: Record<string, any> = {};
      if (debouncedSearch) params.search = debouncedSearch;
      if (selectedStatus) params.status = selectedStatus;
      if (selectedGroup) params.group_id = selectedGroup;

      const res = await exportService.requestExport({
        type: 'drivers',
        params,
      });
      setActiveExportId(res.data.id);
      setIsExportModalOpen(true);
      addToast({
        type: 'info',
        message: ar.exports.exportStarted,
      });
    } catch (e: any) {
      console.error('Export failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setIsExporting(false);
    }
  };

  const handleDownloadDriverPdf = async (driver: Driver) => {
    setDownloadingPdfId(driver.id);
    try {
      await exportService.downloadDriverPdf(driver.id, driver.name ?? undefined);
      addToast({
        type: 'success',
        message: ar.exports.statusDone,
      });
    } catch (e: any) {
      console.error('Driver PDF download failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setDownloadingPdfId(null);
    }
  };

  // Debounce search input
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(search);
      setCurrentPage(1);
    }, 400);
    return () => clearTimeout(handler);
  }, [search]);

  // Load WhatsApp groups for filter dropdown
  useEffect(() => {
    const loadGroups = async () => {
      try {
        const res = await driverService.getWhatsappGroups();
        setGroups(res.data);
      } catch (err) {
        console.error('Failed to load groups', err);
      }
    };
    loadGroups();
  }, []);

  // Fetch drivers list
  const fetchDrivers = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await driverService.getDrivers({
        page: currentPage,
        per_page: perPage,
        search: debouncedSearch || undefined,
        status: selectedStatus || undefined,
        group_id: selectedGroup || undefined,
        sort_by: sortBy,
        sort_direction: sortDirection,
        pool: 'fleet',
      });

      setDrivers(response.data);
      setCurrentPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
      setPerPage(response.meta.per_page);
    } catch (err) {
      console.error('Failed to fetch drivers', err);
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, perPage, debouncedSearch, selectedStatus, selectedGroup, sortBy, sortDirection]);

  useEffect(() => {
    fetchDrivers();
  }, [fetchDrivers]);

  const handleResetFilters = () => {
    setSearch('');
    setDebouncedSearch('');
    setSelectedStatus('');
    setSelectedGroup('');
    setCurrentPage(1);
  };

  const handleSort = (columnId: string) => {
    if (sortBy === columnId) {
      setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(columnId);
      setSortDirection('asc');
    }
    setCurrentPage(1);
  };

  const handleOpenDetail = (driver: Driver) => {
    setSelectedDriverId(driver.id);
    setIsDetailDrawerOpen(true);
  };

  const handleDriverUpdated = (updatedDriver: Driver) => {
    setDrivers((prev) =>
      prev.map((d) => (d.id === updatedDriver.id ? updatedDriver : d))
    );
  };

  const handleDriverCreated = () => {
    fetchDrivers();
  };

  // Statuses list for filter
  const allStatuses: { key: DriverStatusValue; label: string }[] = [
    { key: 'new', label: ar.statuses.new },
    { key: 'contacted', label: ar.statuses.contacted },
    { key: 'qualified', label: ar.statuses.qualified },
    { key: 'training_scheduled', label: ar.statuses.training_scheduled },
    { key: 'trained', label: ar.statuses.trained },
    { key: 'active', label: ar.statuses.active },
    { key: 'declined', label: ar.statuses.declined },
    { key: 'no_response', label: ar.statuses.no_response },
    { key: 'suspended', label: ar.statuses.suspended },
    { key: 'blocked', label: ar.statuses.blocked },
    { key: 'opted_out', label: ar.statuses.opted_out },
  ];

  const columns: ColumnDef<Driver>[] = [
    {
      id: 'phone',
      header: ar.drivers.phone,
      sortable: true,
      render: (driver) => (
        <span className="font-mono text-sm font-semibold text-primary" dir="ltr">
          {driver.phone}
        </span>
      ),
    },
    {
      id: 'name',
      header: ar.drivers.name,
      sortable: true,
      isPrimary: true,
      render: (driver) => (
        <div className="flex items-center gap-2 flex-wrap">
          <span className="font-medium text-default-900">
            {driver.name || <span className="text-default-400 italic text-xs">{ar.drivers.unnamed}</span>}
          </span>
          {driver.is_new_to_fleet && (
            <Chip
              size="sm"
              color="success"
              variant="flat"
              className="text-[11px] font-bold border border-success-200"
            >
              {ar.drivers.newToFleetBadge}
            </Chip>
          )}
        </div>
      ),
    },
    {
      id: 'groups',
      header: ar.drivers.groups,
      render: (driver) => (
        <div className="flex flex-wrap gap-1 max-w-xs">
          {driver.groups && driver.groups.length > 0 ? (
            driver.groups.map((g) => (
              <Chip key={g.id} size="sm" variant="flat" color="default" className="text-xs">
                {g.name}
              </Chip>
            ))
          ) : (
            <span className="text-xs text-default-400">-</span>
          )}
        </div>
      ),
    },
    {
      id: 'status',
      header: ar.drivers.status,
      sortable: true,
      render: (driver) => (
        <DriverStatusBadge status={driver.status.value} label={driver.status.label} />
      ),
    },
    {
      id: 'trips_completed',
      header: ar.driverMetrics.tripsCompleted,
      sortable: true,
      render: (driver) => (
        <span className="font-semibold text-xs text-foreground">
          {driver.metrics?.trips_completed ?? 0}
        </span>
      ),
    },
    {
      id: 'avg_rating',
      header: ar.driverMetrics.avgRating,
      sortable: true,
      render: (driver) =>
        driver.metrics?.avg_rating ? (
          <span className="text-xs font-bold text-amber-500 flex items-center gap-1">
            ⭐ {driver.metrics.avg_rating}
          </span>
        ) : (
          <span className="text-xs text-default-400">{ar.driverMetrics.unrated}</span>
        ),
    },
    {
      id: 'no_shows',
      header: ar.driverMetrics.noShows,
      sortable: true,
      render: (driver) => {
        const noShows = driver.metrics?.no_shows ?? 0;
        return (
          <span
            className={`text-xs font-medium ${
              noShows > 0 ? 'text-danger font-bold' : 'text-default-500'
            }`}
          >
            {noShows}
          </span>
        );
      },
    },
    {
      id: 'created_at',
      header: ar.drivers.joinedAt,
      sortable: true,
      render: (driver) => (
        <span className="text-xs text-default-500">
          {new Date(driver.created_at).toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
          })}
        </span>
      ),
    },
    {
      id: 'actions',
      header: ar.common.actions,
      className: 'text-end',
      headerClassName: 'text-end',
      render: (driver) => (
        <div className="flex justify-end gap-1.5">
          <Button
            size="sm"
            variant="light"
            color="secondary"
            isIconOnly
            className="min-w-[40px] min-h-[40px]"
            isLoading={downloadingPdfId === driver.id}
            onPress={() => handleDownloadDriverPdf(driver)}
            title={ar.exports.exportDriverPdf}
          >
            <DocumentTextIcon className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="light"
            color="success"
            isIconOnly
            className="min-w-[40px] min-h-[40px]"
            onPress={() => setChatDriver(driver)}
            title={ar.whatsapp.driverChat}
          >
            <ChatBubbleLeftRightIcon className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="light"
            color="primary"
            isIconOnly
            className="min-w-[40px] min-h-[40px]"
            onPress={() => handleOpenDetail(driver)}
            title={ar.drivers.driverDetails}
          >
            <EyeIcon className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PageContainer className="flex flex-col gap-6">
      <PageHeader
        title={ar.drivers.title}
        description={ar.drivers.subtitle}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              color="success"
              variant="flat"
              className="min-h-[44px]"
              startContent={<PaperAirplaneIcon className="h-4 w-4" />}
              onPress={() => setIsBulkInviteOpen(true)}
            >
              {ar.whatsapp.bulkInvite}
            </Button>
            <Button
              color="primary"
              variant="solid"
              className="min-h-[44px]"
              startContent={<UserPlusIcon className="h-4 w-4" />}
              onPress={() => setIsAddModalOpen(true)}
            >
              {ar.drivers.addDriver}
            </Button>
            <Button
              color="secondary"
              variant="flat"
              className="min-h-[44px]"
              startContent={<DocumentArrowDownIcon className="h-4 w-4" />}
              onPress={() => {
                startTransition(() => {
                  navigate('/import');
                });
              }}
            >
              {ar.drivers.importDrivers}
            </Button>
            <Button
              color="default"
              variant="flat"
              className="min-h-[44px]"
              startContent={<DocumentArrowDownIcon className="h-4 w-4" />}
              isLoading={isExporting}
              onPress={handleExportDrivers}
            >
              {ar.exports.exportDrivers}
            </Button>
          </div>
        }
      />

      {/* Filter and Search Bar */}
      <div className="flex flex-col gap-3 rounded-2xl border border-default-200 bg-content1 p-4 shadow-sm">
        <div className="flex items-center gap-2">
          <div className="flex-1 min-w-0">
            <SearchInput
              placeholder={ar.drivers.searchPlaceholder}
              value={search}
              onSearch={(val) => {
                setSearch(val);
                setDebouncedSearch(val);
                setCurrentPage(1);
              }}
              className="w-full"
            />
          </div>
          <Button
            variant="flat"
            size="sm"
            color={selectedStatus || selectedGroup ? 'primary' : 'default'}
            className="md:hidden min-h-[44px] min-w-[44px] shrink-0"
            onPress={() => setShowMobileFilters(!showMobileFilters)}
            startContent={<FunnelIcon className="h-4 w-4" />}
          >
            تصفية
            {(selectedStatus || selectedGroup) && (
              <Chip size="sm" color="primary" variant="solid" className="h-4 text-[10px] px-1 ms-1">
                {(selectedStatus ? 1 : 0) + (selectedGroup ? 1 : 0)}
              </Chip>
            )}
          </Button>
        </div>

        {/* Secondary collapsible filters */}
        <div
          className={`${
            showMobileFilters ? 'flex' : 'hidden'
          } md:flex flex-col sm:flex-row flex-wrap items-stretch md:items-center gap-3 pt-2 md:pt-0 border-t md:border-t-0 border-default-100`}
        >
          <div className="w-full sm:w-48">
            <Select
              placeholder={ar.drivers.allStatuses}
              selectedKeys={selectedStatus ? [selectedStatus] : []}
              onChange={(e) => {
                setSelectedStatus(e.target.value);
                setCurrentPage(1);
              }}
              size="sm"
              variant="bordered"
              aria-label={ar.drivers.allStatuses}
            >
              {allStatuses.map((s) => (
                <SelectItem key={s.key} textValue={s.label}>
                  {s.label}
                </SelectItem>
              ))}
            </Select>
          </div>

          <div className="w-full sm:w-48">
            <Select
              placeholder={ar.drivers.allGroups}
              selectedKeys={selectedGroup ? [selectedGroup] : []}
              onChange={(e) => {
                setSelectedGroup(e.target.value);
                setCurrentPage(1);
              }}
              size="sm"
              variant="bordered"
              aria-label={ar.drivers.allGroups}
            >
              {groups.map((g) => (
                <SelectItem key={String(g.id)} textValue={g.name}>
                  {g.name}
                </SelectItem>
              ))}
            </Select>
          </div>

          {(search || selectedStatus || selectedGroup) && (
            <Button
              size="sm"
              variant="flat"
              color="default"
              startContent={<ArrowPathIcon className="h-3.5 w-3.5" />}
              onPress={handleResetFilters}
              className="self-start sm:self-auto min-h-[44px]"
            >
              {ar.activity.resetFilters}
            </Button>
          )}
        </div>
      </div>

      {/* Drivers Data Table */}
      <DataTable
        columns={columns}
        data={drivers}
        keyExtractor={(driver) => driver.id}
        isLoading={isLoading}
        emptyTitle={ar.drivers.noDriversFound}
        emptyDescription="جرب تغيير كلمات البحث أو إعادة تعيين الفلاتر."
        emptyIcon={<UsersIcon className="h-10 w-10 text-default-300" />}
        currentPage={currentPage}
        lastPage={lastPage}
        total={total}
        onPageChange={(page) => setCurrentPage(page)}
        sortBy={sortBy}
        sortDirection={sortDirection}
        onSort={handleSort}
      />

      {/* Add Driver Modal */}
      <AddDriverModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onSuccess={handleDriverCreated}
        existingGroups={groups}
      />

      {/* Driver Detail Drawer */}
      <DriverDetailDrawer
        driverId={selectedDriverId}
        isOpen={isDetailDrawerOpen}
        onClose={() => {
          setIsDetailDrawerOpen(false);
          setSelectedDriverId(null);
        }}
        onDriverUpdated={handleDriverUpdated}
      />

      {/* WhatsApp Bulk Invite Modal */}
      <BulkInviteModal
        isOpen={isBulkInviteOpen}
        onClose={() => setIsBulkInviteOpen(false)}
        onSuccess={fetchDrivers}
      />

      {/* Driver WhatsApp Chat Modal */}
      {chatDriver && (
        <DriverChatModal
          isOpen={Boolean(chatDriver)}
          driverId={chatDriver.id}
          driverName={chatDriver.name}
          driverPhone={chatDriver.phone}
          onClose={() => setChatDriver(null)}
          onStatusChanged={fetchDrivers}
        />
      )}
      {/* Export Modal */}
      <ExportModal
        isOpen={isExportModalOpen}
        onClose={() => setIsExportModalOpen(false)}
        activeExportId={activeExportId}
      />
    </PageContainer>
  );
};

export default DriversPage;
