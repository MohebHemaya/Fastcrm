import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Button,
  Select,
  SelectItem,
  Chip,
  Card,
  CardBody,
} from '@heroui/react';
import {
  UserPlusIcon,
  ArrowPathIcon,
  EyeIcon,
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  CheckCircleIcon,
  InformationCircleIcon,
} from '@/components/icons';
import PageContainer from '@/components/layout/PageContainer';
import { SearchInput } from '@/components/form';
import PageHeader from '@/components/ui/PageHeader';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { ar } from '@/i18n/ar';
import driverService from '@/services/driverService';
import { whatsappService } from '@/services/whatsappService';
import type { Driver, WhatsappGroup } from '@/types/driver';
import DriverStatusBadge from '@/features/drivers/components/DriverStatusBadge';
import AddDriverModal from '@/features/drivers/components/AddDriverModal';
import DriverDetailDrawer from '@/features/drivers/components/DriverDetailDrawer';
import BulkInviteModal from '@/features/whatsapp/components/BulkInviteModal';
import DriverChatModal from '@/features/whatsapp/components/DriverChatModal';
import { useUiStore } from '@/lib/stores/useUiStore';

export const NewDriversPage: React.FC = () => {
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

  // Modals state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [selectedDriverId, setSelectedDriverId] = useState<number | null>(null);
  const [isDetailDrawerOpen, setIsDetailDrawerOpen] = useState(false);
  const [isBulkInviteOpen, setIsBulkInviteOpen] = useState(false);
  const [chatDriver, setChatDriver] = useState<Driver | null>(null);

  // Promoting driver state
  const [promotingDriver, setPromotingDriver] = useState<Driver | null>(null);
  const [isPromoting, setIsPromoting] = useState(false);

  // Sending question loading per driver ID
  const [actionLoadingDriverId, setActionLoadingDriverId] = useState<number | null>(null);

  const addToast = useUiStore((state) => state.addToast);

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

  const statusOptions = useMemo(
    () => [
      { key: 'all', label: ar.newDrivers.filterAllStatuses },
      { key: 'new', label: ar.newDrivers.statusNew },
      { key: 'questionnaire_in_progress', label: ar.newDrivers.statusInProgress },
    ],
    []
  );

  const groupOptions = useMemo(
    () => [
      { key: 'all', label: ar.drivers.allGroups },
      ...groups.map((g) => ({ key: String(g.id), label: g.name })),
    ],
    [groups]
  );

  // Fetch new drivers list (pool: 'new')
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
        pool: 'new',
      });

      setDrivers(response.data);
      setCurrentPage(response.meta.current_page);
      setLastPage(response.meta.last_page);
      setTotal(response.meta.total);
      setPerPage(response.meta.per_page);
    } catch (err) {
      console.error('Failed to fetch new drivers', err);
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

  // Start sending questionnaire / invite to a single new driver
  const handleStartQuestionnaire = async (driver: Driver) => {
    setActionLoadingDriverId(driver.id);
    try {
      await whatsappService.inviteDrivers({
        driver_ids: [driver.id],
      });
      addToast({
        type: 'success',
        message: ar.newDrivers.inviteSuccess,
      });
      fetchDrivers();
    } catch (err: any) {
      console.error('Failed to send questions to driver', err);
      addToast({
        type: 'error',
        message: err?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setActionLoadingDriverId(null);
    }
  };

  // Resend current unanswered question to in-progress driver
  const handleResendQuestion = async (driver: Driver) => {
    setActionLoadingDriverId(driver.id);
    try {
      await driverService.resendQuestion(driver.id);
      addToast({
        type: 'success',
        message: ar.newDrivers.resendSuccess,
      });
    } catch (err: any) {
      console.error('Failed to resend question', err);
      addToast({
        type: 'error',
        message: err?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setActionLoadingDriverId(null);
    }
  };

  // Promote new driver manually to active fleet
  const handlePromoteToFleet = async () => {
    if (!promotingDriver) return;
    setIsPromoting(true);
    try {
      await driverService.changeStatus(promotingDriver.id, {
        status: 'active',
        reason: 'اعتماد ونقل يدوي إلى السائقين اللي معانا',
      });
      addToast({
        type: 'success',
        message: ar.newDrivers.promoteSuccess,
      });
      setPromotingDriver(null);
      fetchDrivers();
    } catch (err: any) {
      console.error('Failed to promote driver', err);
      addToast({
        type: 'error',
        message: err?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setIsPromoting(false);
    }
  };

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
        <span className="font-medium text-default-900">
          {driver.name || <span className="text-default-400 italic text-xs">{ar.drivers.unnamed}</span>}
        </span>
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
      id: 'progress',
      header: ar.newDrivers.progressColumn,
      render: (driver) => {
        const count = driver.answers_count ?? 0;
        const isInProgress = driver.status.value === 'questionnaire_in_progress';
        return (
          <div className="flex items-center gap-1.5">
            <Chip
              size="sm"
              variant="flat"
              color={count > 0 ? (isInProgress ? 'warning' : 'primary') : 'default'}
              className="text-xs font-semibold"
            >
              {ar.newDrivers.answersCount(count)}
            </Chip>
            {isInProgress && (
              <span className="text-[11px] text-warning-600 font-medium">
                (جاري الإجابة)
              </span>
            )}
          </div>
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
      className: 'text-end whitespace-nowrap min-w-[340px]',
      headerClassName: 'text-end min-w-[340px]',
      render: (driver) => {
        const isActionLoading = actionLoadingDriverId === driver.id;
        const isNew = driver.status.value === 'new';
        const isInProgress = driver.status.value === 'questionnaire_in_progress';

        return (
          <div className="flex items-center justify-end gap-1.5 flex-nowrap">
            {isNew && (
              <Button
                size="sm"
                color="primary"
                variant="flat"
                isLoading={isActionLoading}
                startContent={!isActionLoading && <PaperAirplaneIcon className="h-3.5 w-3.5 rtl:-rotate-90 shrink-0" />}
                onPress={() => handleStartQuestionnaire(driver)}
                className="font-bold text-xs h-8 px-2.5 shrink-0"
                title={ar.newDrivers.sendQuestionsTooltip}
              >
                {ar.newDrivers.sendQuestions}
              </Button>
            )}

            {isInProgress && (
              <Button
                size="sm"
                color="warning"
                variant="flat"
                isLoading={isActionLoading}
                startContent={!isActionLoading && <ArrowPathIcon className="h-3.5 w-3.5 shrink-0" />}
                onPress={() => handleResendQuestion(driver)}
                className="font-bold text-xs h-8 px-2.5 shrink-0"
                title={ar.newDrivers.resendQuestionTooltip}
              >
                {ar.newDrivers.resendCurrentQuestion}
              </Button>
            )}

            <Button
              size="sm"
              color="success"
              variant="flat"
              startContent={<CheckCircleIcon className="h-3.5 w-3.5 text-success-600 shrink-0" />}
              onPress={() => setPromotingDriver(driver)}
              className="font-semibold text-xs text-success-700 bg-success-50 hover:bg-success-100 h-8 px-2.5 shrink-0 border border-success-200"
              title={ar.newDrivers.promoteToFleetTooltip}
            >
              اعتماد للأسطول
            </Button>

            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="success"
              className="text-default-500 hover:text-success hover:bg-success-50 min-w-8 w-8 h-8 rounded-lg shrink-0"
              onPress={() => setChatDriver(driver)}
              aria-label={ar.whatsapp.driverChat}
              title={ar.whatsapp.driverChat}
            >
              <ChatBubbleLeftRightIcon className="h-4 w-4" />
            </Button>

            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="primary"
              className="text-default-500 hover:text-primary hover:bg-primary-50 min-w-8 w-8 h-8 rounded-lg shrink-0"
              onPress={() => handleOpenDetail(driver)}
              aria-label={ar.drivers.driverDetails}
              title={ar.drivers.driverDetails}
            >
              <EyeIcon className="h-4 w-4" />
            </Button>
          </div>
        );
      },
    },
  ];

  return (
    <PageContainer className="flex flex-col gap-6">
      {/* Top Header */}
      <PageHeader
        title={ar.newDrivers.title}
        description={ar.newDrivers.subtitle}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.newDrivers.title },
        ]}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Button
              color="primary"
              variant="solid"
              startContent={<PaperAirplaneIcon className="h-4 w-4 rtl:-rotate-90" />}
              onPress={() => setIsBulkInviteOpen(true)}
              className="font-bold shadow-sm shadow-primary/20 min-h-[44px]"
            >
              {ar.newDrivers.bulkInviteButton}
            </Button>
            <Button
              color="default"
              variant="flat"
              startContent={<UserPlusIcon className="h-4 w-4" />}
              onPress={() => setIsAddModalOpen(true)}
              className="font-semibold min-h-[44px]"
            >
              {ar.drivers.addDriver}
            </Button>
          </div>
        }
      />

      {/* Guide Banner */}
      <Card className="bg-primary-50/50 border border-primary-100 shadow-none">
        <CardBody className="p-3.5 sm:p-4 flex flex-row items-center gap-3">
          <div className="p-2 bg-primary-100 rounded-xl text-primary shrink-0">
            <InformationCircleIcon className="h-5 w-5" />
          </div>
          <div className="text-xs text-primary-950 flex-1 leading-relaxed">
            <span className="font-bold">دورة حياة السائق الجديد: </span>
            يتم إرسال أسئلة الاستبيان للسائق عبر واتساب، وعندما يكمل الإجابة على جميع الأسئلة ينتقل تلقائياً إلى قائمة{' '}
            <span className="font-bold text-success-700">السائقين اللي معانا</span> مع علامة{' '}
            <span className="font-bold text-success-800 bg-success-100 px-1 py-0.5 rounded">جديد ✨</span>{' '}
            حتى ينفذ أول أمر شغل. كما يمكنك اعتماده ونقله فوراً في أي وقت بالضغط على «اعتماد للأسطول».
          </div>
        </CardBody>
      </Card>

      {/* Filters Bar */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 rounded-2xl border border-default-200 bg-content1 p-4 shadow-sm">
        <div className="w-full md:w-80">
          <SearchInput
            placeholder={ar.drivers.searchPlaceholder}
            value={search}
            onSearch={(term) => {
              setSearch(term);
              setDebouncedSearch(term);
              setCurrentPage(1);
            }}
            className="w-full"
          />
        </div>

        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div className="w-full sm:w-56">
            <Select
              placeholder={ar.newDrivers.filterAllStatuses}
              selectedKeys={selectedStatus ? [selectedStatus] : ['all']}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedStatus(val === 'all' ? '' : val);
                setCurrentPage(1);
              }}
              size="sm"
              variant="bordered"
              aria-label={ar.drivers.status}
              classNames={{
                trigger: 'min-h-[42px] h-[42px] bg-background border-default-300 dark:border-default-700',
              }}
            >
              {statusOptions.map((s) => (
                <SelectItem key={s.key} textValue={s.label}>
                  {s.label}
                </SelectItem>
              ))}
            </Select>
          </div>

          <div className="w-full sm:w-56">
            <Select
              placeholder={ar.drivers.allGroups}
              selectedKeys={selectedGroup ? [selectedGroup] : ['all']}
              onChange={(e) => {
                const val = e.target.value;
                setSelectedGroup(val === 'all' ? '' : val);
                setCurrentPage(1);
              }}
              size="sm"
              variant="bordered"
              aria-label={ar.drivers.groups}
              classNames={{
                trigger: 'min-h-[42px] h-[42px] bg-background border-default-300 dark:border-default-700',
              }}
            >
              {groupOptions.map((g) => (
                <SelectItem key={g.key} textValue={g.label}>
                  {g.label}
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
              className="min-h-[42px] h-[42px]"
            >
              {ar.activity.resetFilters}
            </Button>
          )}
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={drivers}
        keyExtractor={(driver) => driver.id}
        isLoading={isLoading}
        emptyTitle={ar.newDrivers.noDriversFound}
        emptyDescription={ar.newDrivers.noDriversDescription}
        emptyIcon={<UserPlusIcon className="h-10 w-10 text-default-300" />}
        currentPage={currentPage}
        lastPage={lastPage}
        total={total}
        onPageChange={(page) => setCurrentPage(page)}
        sortBy={sortBy}
        sortDirection={sortDirection}
        onSort={handleSort}
      />

      {/* Confirm Manual Promotion Dialog */}
      <ConfirmDialog
        isOpen={Boolean(promotingDriver)}
        onClose={() => setPromotingDriver(null)}
        onConfirm={handlePromoteToFleet}
        title={ar.newDrivers.promoteConfirmTitle}
        message={ar.newDrivers.promoteConfirmMessage(promotingDriver?.name || promotingDriver?.phone || '')}
        confirmText={ar.newDrivers.promoteConfirmButton}
        variant="primary"
        isLoading={isPromoting}
      />

      {/* Add Driver Modal */}
      <AddDriverModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onSuccess={fetchDrivers}
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
    </PageContainer>
  );
};

export default NewDriversPage;
