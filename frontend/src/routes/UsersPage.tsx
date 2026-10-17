import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Button,
  Chip,
  Card,
  CardBody,
  Tabs,
  Tab,
  Tooltip,
} from '@heroui/react';
import {
  UserPlusIcon,
  UserGroupIcon,
  CheckCircleIcon,
  XCircleIcon,
  PencilSquareIcon,
  TrashIcon,
  CalendarIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';
import PageContainer from '@/components/layout/PageContainer';
import PageHeader from '@/components/ui/PageHeader';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { SearchInput } from '@/components/form';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import UserModal from '@/features/users/components/UserModal';
import { userService } from '@/services/userService';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { useUiStore } from '@/lib/stores/useUiStore';
import type { User, CreateUserData, UpdateUserData } from '@/types/user';
import { ar } from '@/i18n/ar';

export const UsersPage: React.FC = () => {
  const { user: currentUser } = useAuthStore();
  const { addToast } = useUiStore();

  const [users, setUsers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  // Filters
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'true' | 'false'>('all');

  // Modals state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [deleteTargetUser, setDeleteTargetUser] = useState<User | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  // Summary counts
  const [activeCount, setActiveCount] = useState(0);
  const [inactiveCount, setInactiveCount] = useState(0);

  // Fetch users
  const fetchUsers = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await userService.getUsers({
        page: currentPage,
        per_page: 15,
        search: debouncedSearch || undefined,
        is_active: statusFilter === 'all' ? undefined : statusFilter,
      });

      setUsers(res.data || []);
      setCurrentPage(res.meta.current_page);
      setLastPage(res.meta.last_page);
      setTotal(res.meta.total);

      // If viewing all, calculate quick KPI counts
      if (statusFilter === 'all' && !debouncedSearch) {
        const active = (res.data || []).filter((u) => u.is_active).length;
        const inactive = (res.data || []).length - active;
        setActiveCount(active);
        setInactiveCount(inactive);
      }
    } catch {
      addToast({
        type: 'error',
        message: ar.errors.generalError,
      });
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, debouncedSearch, statusFilter, addToast]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  // Create or Update user handler
  const handleModalSubmit = async (data: CreateUserData | UpdateUserData) => {
    if (selectedUser) {
      const res = await userService.updateUser(selectedUser.id, data as UpdateUserData);
      addToast({
        type: 'success',
        message: res.message || ar.users.userUpdated,
      });
    } else {
      const res = await userService.createUser(data as CreateUserData);
      addToast({
        type: 'success',
        message: res.message || ar.users.userCreated,
      });
    }
    fetchUsers();
  };

  // Toggle active handler
  const handleToggleActive = async (user: User) => {
    if (user.id === currentUser?.id) {
      addToast({
        type: 'warning',
        message: ar.users.cannotDeactivateSelf,
      });
      return;
    }

    try {
      const res = await userService.toggleActive(user.id);
      addToast({
        type: 'success',
        message: res.message || ar.users.statusUpdated,
      });
      setUsers((prev) =>
        prev.map((u) => (u.id === user.id ? { ...u, is_active: !u.is_active } : u))
      );
    } catch (err: any) {
      addToast({
        type: 'error',
        message: err?.response?.data?.message || ar.errors.generalError,
      });
    }
  };

  // Delete user handler
  const handleDeleteUser = async () => {
    if (!deleteTargetUser) return;
    setIsDeleting(true);
    try {
      await userService.deleteUser(deleteTargetUser.id);
      addToast({
        type: 'success',
        message: ar.users.userDeleted,
      });
      setDeleteTargetUser(null);
      fetchUsers();
    } catch (err: any) {
      addToast({
        type: 'error',
        message: err?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setIsDeleting(false);
    }
  };

  // Table columns definition
  const columns = useMemo<ColumnDef<User>[]>(
    () => [
      {
        id: 'name',
        header: ar.users.name,
        isPrimary: true,
        render: (u) => {
          const isSelf = u.id === currentUser?.id;
          const initial = u.name ? u.name.trim().charAt(0).toUpperCase() : '?';
          return (
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center shrink-0 border border-primary/20">
                {initial}
              </div>
              <div className="flex flex-col">
                <div className="flex items-center gap-2">
                  <span className="font-bold text-default-900 text-sm">{u.name}</span>
                  {isSelf && (
                    <Chip
                      size="sm"
                      color="primary"
                      variant="flat"
                      className="h-4 text-[10px] font-bold px-1"
                    >
                      {ar.users.currentUser}
                    </Chip>
                  )}
                </div>
                <span className="text-xs text-default-400 font-mono" dir="ltr">
                  {u.email}
                </span>
              </div>
            </div>
          );
        },
      },
      {
        id: 'status',
        header: ar.users.status,
        render: (u) =>
          u.is_active ? (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-success-50 text-success-700 border border-success-200">
              <span className="w-1.5 h-1.5 rounded-full bg-success" />
              {ar.users.active}
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-default-100 text-default-600 border border-default-200">
              <span className="w-1.5 h-1.5 rounded-full bg-default-400" />
              {ar.users.inactive}
            </span>
          ),
      },
      {
        id: 'last_login',
        header: ar.users.lastLogin,
        render: (u) => (
          <div className="flex items-center gap-1.5 text-xs text-default-500">
            <ClockIcon className="w-3.5 h-3.5 text-default-400" />
            <span>
              {u.last_login_at
                ? new Date(u.last_login_at).toLocaleString('ar-EG', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })
                : ar.users.neverLoggedIn}
            </span>
          </div>
        ),
      },
      {
        id: 'created_at',
        header: ar.users.createdAt,
        render: (u) => (
          <div className="flex items-center gap-1.5 text-xs text-default-500">
            <CalendarIcon className="w-3.5 h-3.5 text-default-400" />
            <span>
              {new Date(u.created_at).toLocaleDateString('ar-EG', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
              })}
            </span>
          </div>
        ),
      },
      {
        id: 'actions',
        header: ar.users.actions,
        className: 'text-end',
        headerClassName: 'text-end',
        render: (u) => {
          const isSelf = u.id === currentUser?.id;
          return (
            <div className="flex items-center justify-end gap-1">
              <Tooltip content={ar.users.editUser}>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  color="default"
                  onPress={() => {
                    setSelectedUser(u);
                    setIsModalOpen(true);
                  }}
                  className="min-w-[36px] min-h-[36px]"
                  aria-label={ar.users.editUser}
                >
                  <PencilSquareIcon className="w-4 h-4 text-default-600" />
                </Button>
              </Tooltip>

              <Tooltip
                content={
                  isSelf
                    ? ar.users.cannotDeactivateSelf
                    : u.is_active
                    ? 'تعطيل الحساب'
                    : 'تفعيل الحساب'
                }
              >
                <span>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    color={u.is_active ? 'warning' : 'success'}
                    isDisabled={isSelf}
                    onPress={() => handleToggleActive(u)}
                    className="min-w-[36px] min-h-[36px]"
                    aria-label="تغيير الحالة"
                  >
                    {u.is_active ? (
                      <XCircleIcon className="w-4 h-4 text-warning-600" />
                    ) : (
                      <CheckCircleIcon className="w-4 h-4 text-emerald-600" />
                    )}
                  </Button>
                </span>
              </Tooltip>

              <Tooltip content={isSelf ? ar.users.cannotDeleteSelf : ar.users.deleteUser}>
                <span>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    color="danger"
                    isDisabled={isSelf}
                    onPress={() => setDeleteTargetUser(u)}
                    className="min-w-[36px] min-h-[36px]"
                    aria-label={ar.users.deleteUser}
                  >
                    <TrashIcon className="w-4 h-4 text-danger-500" />
                  </Button>
                </span>
              </Tooltip>
            </div>
          );
        },
      },
    ],
    [currentUser?.id]
  );

  return (
    <PageContainer className="space-y-6">
      <PageHeader
        title={ar.users.title}
        description={ar.users.subtitle}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.nav.settings, href: '/settings' },
          { label: ar.users.title },
        ]}
        actions={
          <Button
            color="primary"
            variant="solid"
            startContent={<UserPlusIcon className="w-4 h-4" />}
            onPress={() => {
              setSelectedUser(null);
              setIsModalOpen(true);
            }}
            className="font-bold min-h-[44px]"
          >
            {ar.users.addUser}
          </Button>
        }
      />

      {/* KPI Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {/* Total Users */}
        <Card className="border border-default-200 shadow-none bg-content1">
          <CardBody className="p-4 flex items-center gap-3.5">
            <UserGroupIcon className="w-5 h-5 text-default-400 shrink-0" />
            <div>
              <p className="text-xs text-default-500 font-medium">{ar.users.totalUsers}</p>
              <p className="text-2xl font-bold text-default-900" dir="ltr">
                {total}
              </p>
            </div>
          </CardBody>
        </Card>

        {/* Active Users */}
        <Card className="border border-default-200 shadow-none bg-content1">
          <CardBody className="p-4 flex items-center gap-3.5">
            <CheckCircleIcon className="w-5 h-5 text-default-400 shrink-0" />
            <div>
              <p className="text-xs text-default-500 font-medium">{ar.users.activeUsers}</p>
              <p className="text-2xl font-bold text-default-900" dir="ltr">
                {activeCount || users.filter((u) => u.is_active).length}
              </p>
            </div>
          </CardBody>
        </Card>

        {/* Inactive Users */}
        <Card className="border border-default-200 shadow-none bg-content1">
          <CardBody className="p-4 flex items-center gap-3.5">
            <XCircleIcon className="w-5 h-5 text-default-400 shrink-0" />
            <div>
              <p className="text-xs text-default-500 font-medium">{ar.users.inactiveUsers}</p>
              <p className="text-2xl font-bold text-default-700" dir="ltr">
                {inactiveCount || users.filter((u) => !u.is_active).length}
              </p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Filter and Search Bar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-4 rounded-xl border border-default-200 bg-content1 shadow-none">
        <div className="flex-1 max-w-md">
          <SearchInput
            placeholder={ar.users.searchPlaceholder}
            value={search}
            onSearch={(val) => {
              setSearch(val);
              setDebouncedSearch(val);
              setCurrentPage(1);
            }}
          />
        </div>

        <div className="overflow-x-auto pb-1 sm:pb-0">
          <Tabs
            selectedKey={statusFilter}
            onSelectionChange={(key) => {
              setStatusFilter(key as any);
              setCurrentPage(1);
            }}
            color="primary"
            variant="solid"
            disableAnimation
            classNames={{
              tabList: 'bg-default-100 p-1.5 rounded-xl whitespace-nowrap gap-1 border border-default-200/50',
              tab: 'data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground font-semibold text-xs px-3.5 py-1.5 rounded-lg transition-colors',
            }}
          >
            <Tab key="all" title={ar.users.allStatuses} />
            <Tab key="true" title={ar.users.active} />
            <Tab key="false" title={ar.users.inactive} />
          </Tabs>
        </div>
      </div>

      {/* Users DataTable */}
      <DataTable
        columns={columns}
        data={users}
        keyExtractor={(u) => u.id}
        isLoading={isLoading}
        emptyTitle={ar.users.emptyTitle}
        emptyDescription={ar.users.emptyDescription}
        emptyIcon={<UserGroupIcon className="w-10 h-10 text-default-300" />}
        currentPage={currentPage}
        lastPage={lastPage}
        total={total}
        onPageChange={setCurrentPage}
      />

      {/* Create / Edit User Modal */}
      <UserModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        user={selectedUser}
        currentUserId={currentUser?.id ?? null}
        onSubmit={handleModalSubmit}
      />

      {/* Delete Confirmation Dialog */}
      <ConfirmDialog
        isOpen={Boolean(deleteTargetUser)}
        onClose={() => setDeleteTargetUser(null)}
        onConfirm={handleDeleteUser}
        title={ar.users.deleteConfirmTitle}
        message={`${ar.users.deleteConfirmMessage} (${deleteTargetUser?.name || ''})`}
        variant="danger"
        confirmText={ar.common.delete}
        cancelText={ar.common.cancel}
        isLoading={isDeleting}
      />
    </PageContainer>
  );
};

export default UsersPage;
