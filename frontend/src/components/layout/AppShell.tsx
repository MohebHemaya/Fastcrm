import React, { useState, useEffect, Suspense } from 'react';
import { NavLink, Outlet, useLocation, useNavigate, Link } from 'react-router-dom';
import PageSkeleton from '@/components/ui/PageSkeleton';
import {
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
  Avatar,
  Chip,
  Tooltip,
  Divider,
  ScrollShadow,
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
  DrawerFooter,
} from '@heroui/react';
import {
  Squares2X2Icon,
  Squares2X2IconSolid,
  ClipboardDocumentListIcon,
  ClipboardDocumentListIconSolid,
  UsersIcon,
  UsersIconSolid,
  ArrowUpTrayIcon,
  ArrowUpTrayIconSolid,
  ChatBubbleLeftRightIcon,
  ChatBubbleLeftRightIconSolid,
  QuestionMarkCircleIcon,
  QuestionMarkCircleIconSolid,
  ChartBarIcon,
  ChartBarIconSolid,
  Cog6ToothIcon,
  Cog6ToothIconSolid,
  UserGroupIcon,
  UserGroupIconSolid,
  UserPlusIcon,
  UserPlusIconSolid,
  ClockIcon,
  ClockIconSolid,
  HeartIcon,
  HeartIconSolid,
  PlusIcon,
  Bars3Icon,
  KeyIcon,
  ArrowRightOnRectangleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ChevronLeftIcon,
} from '@/components/icons';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { useUiStore } from '@/lib/stores/useUiStore';
import { useSidebarBadges } from '@/hooks/useSidebarBadges';
import { useBackgroundWhatsAppSync } from '@/hooks/useBackgroundWhatsAppSync';
import { whatsappService } from '@/services/whatsappService';
import type { WhatsAppProviderStatus } from '@/types/whatsapp';
import ToastContainer from '@/components/ui/ToastContainer';
import RealtimeNotifications from '@/components/RealtimeNotifications';
import ChangePasswordDialog from '@/components/auth/ChangePasswordDialog';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { ar } from '@/i18n/ar';

interface NavItemDef {
  label: string;
  path: string;
  outlineIcon: React.ComponentType<{ className?: string }>;
  solidIcon: React.ComponentType<{ className?: string }>;
  badgeKey?: 'orders_open' | 'drivers_needs_review' | 'imports_ready' | 'failed_messages_24h' | 'health_warning' | 'new_drivers';
  badgeColor?: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  isDot?: boolean;
}

interface NavGroupDef {
  title: string;
  items: NavItemDef[];
}

export const AppShell: React.FC = () => {
  const [isMobileDrawerOpen, setIsMobileDrawerOpen] = useState(false);
  const [isPasswordDialogOpen, setIsPasswordDialogOpen] = useState(false);
  const [isLogoutDialogOpen, setIsLogoutDialogOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [providerStatus, setProviderStatus] = useState<WhatsAppProviderStatus | null>(null);

  const { user, logout } = useAuthStore();
  const { isSidebarCollapsed, toggleSidebarCollapsed, addToast } = useUiStore();
  const { badges, refresh: refreshBadges } = useSidebarBadges();
  const navigate = useNavigate();
  const location = useLocation();

  // Run global background sync for WhatsApp bot replies & questionnaires across all CRM pages
  useBackgroundWhatsAppSync(refreshBadges);

  useEffect(() => {
    whatsappService
      .getProviderStatus()
      .then((res) => {
        setProviderStatus(res.data);
      })
      .catch(() => {
        // Fallback default if API is unavailable
      });
  }, []);

  // Close mobile drawer whenever route changes
  useEffect(() => {
    setIsMobileDrawerOpen(false);
  }, [location.pathname]);

  const navGroups: NavGroupDef[] = [
    {
      title: '',
      items: [
        {
          label: ar.nav.dashboard,
          path: '/',
          outlineIcon: Squares2X2Icon,
          solidIcon: Squares2X2IconSolid,
        },
      ],
    },
    {
      title: 'التشغيل اليومي',
      items: [
        {
          label: ar.nav.workOrders,
          path: '/work-orders',
          outlineIcon: ClipboardDocumentListIcon,
          solidIcon: ClipboardDocumentListIconSolid,
          badgeKey: 'orders_open',
          badgeColor: 'warning',
        },
        {
          label: ar.nav.drivers,
          path: '/drivers',
          outlineIcon: UsersIcon,
          solidIcon: UsersIconSolid,
          badgeKey: 'drivers_needs_review',
          badgeColor: 'danger',
        },
        {
          label: ar.nav.newDrivers,
          path: '/new-drivers',
          outlineIcon: UserPlusIcon,
          solidIcon: UserPlusIconSolid,
          badgeKey: 'new_drivers',
          badgeColor: 'primary',
        },
        {
          label: ar.nav.import,
          path: '/import',
          outlineIcon: ArrowUpTrayIcon,
          solidIcon: ArrowUpTrayIconSolid,
        },
      ],
    },
    {
      title: 'التواصل',
      items: [
        {
          label: ar.nav.whatsapp,
          path: '/messages',
          outlineIcon: ChatBubbleLeftRightIcon,
          solidIcon: ChatBubbleLeftRightIconSolid,
          badgeKey: 'failed_messages_24h',
          badgeColor: 'danger',
        },
        {
          label: ar.nav.questions,
          path: '/questions',
          outlineIcon: QuestionMarkCircleIcon,
          solidIcon: QuestionMarkCircleIconSolid,
        },
      ],
    },
    {
      title: 'التحليلات',
      items: [
        {
          label: ar.nav.reports,
          path: '/reports',
          outlineIcon: ChartBarIcon,
          solidIcon: ChartBarIconSolid,
        },
      ],
    },
    {
      title: 'النظام',
      items: [
        {
          label: ar.nav.settings,
          path: '/settings',
          outlineIcon: Cog6ToothIcon,
          solidIcon: Cog6ToothIconSolid,
        },
        {
          label: ar.nav.users,
          path: '/users',
          outlineIcon: UserGroupIcon,
          solidIcon: UserGroupIconSolid,
        },
        {
          label: ar.nav.activityLog,
          path: '/activity-logs',
          outlineIcon: ClockIcon,
          solidIcon: ClockIconSolid,
        },
        {
          label: ar.nav.health,
          path: '/health',
          outlineIcon: HeartIcon,
          solidIcon: HeartIconSolid,
          badgeKey: 'health_warning',
          badgeColor: 'danger',
          isDot: true,
        },
      ],
    },
  ];

  const handleLogout = async () => {
    setIsLoggingOut(true);
    try {
      await logout();
      addToast({
        type: 'info',
        message: ar.auth.logoutSuccess,
      });
      navigate('/login');
    } finally {
      setIsLoggingOut(false);
      setIsLogoutDialogOpen(false);
    }
  };

  const getBadgeValue = (item: NavItemDef): number | null => {
    if (!badges || !item.badgeKey || item.isDot) return null;
    const val = badges[item.badgeKey];
    if (typeof val === 'number' && val > 0) {
      return val;
    }
    return null;
  };

  const hasHealthWarning = (item: NavItemDef): boolean => {
    return Boolean(item.isDot && badges?.health_warning);
  };

  const renderNavGroup = (group: NavGroupDef, isCollapsed: boolean, onItemClick?: () => void) => (
    <div key={group.title || 'main'} className="space-y-1">
      {group.title ? (
        !isCollapsed ? (
          <div className="px-3 pt-3 pb-1 text-[11px] font-bold text-default-400 tracking-wider">
            {group.title}
          </div>
        ) : (
          <Divider className="my-2 bg-default-100" />
        )
      ) : null}

      {group.items.map((item) => {
        const isActive =
          item.path === '/'
            ? location.pathname === '/'
            : location.pathname.startsWith(item.path);

        const Icon = isActive ? item.solidIcon : item.outlineIcon;
        const badgeCount = getBadgeValue(item);
        const showWarningDot = hasHealthWarning(item);

        const linkContent = (
          <NavLink
            key={item.path}
            to={item.path}
            onClick={onItemClick}
            className={`group relative flex items-center gap-3 rounded-lg transition-colors min-h-[44px] ${
              isCollapsed
                ? 'justify-center p-2.5 mx-auto w-11 h-11'
                : 'px-3.5 py-2.5 text-sm font-medium'
            } ${
              isActive
                ? 'bg-primary text-primary-foreground shadow-none font-semibold'
                : 'text-default-600 hover:bg-default-100 text-default-900'
            }`}
          >
            <div className="relative flex items-center justify-center shrink-0">
              <Icon
                className={`h-5 w-5 ${
                  isActive ? 'text-primary-foreground' : 'text-default-400 group-hover:text-default-900'
                }`}
              />
              {isCollapsed && (badgeCount !== null || showWarningDot) && (
                <span
                  className={`absolute -top-1 -end-1 h-2.5 w-2.5 rounded-full ring-2 ring-content1 ${
                    item.badgeColor === 'danger'
                      ? 'bg-danger'
                      : item.badgeColor === 'warning'
                      ? 'bg-warning'
                      : 'bg-primary'
                  }`}
                />
              )}
            </div>

            {!isCollapsed && (
              <>
                <span className="truncate flex-1 text-start">{item.label}</span>
                {badgeCount !== null && (
                  <Chip
                    size="sm"
                    color={item.badgeColor || 'primary'}
                    variant={isActive ? 'solid' : 'flat'}
                    className={`h-5 min-w-5 px-1 text-[10px] font-bold ${
                      isActive ? 'bg-primary-foreground text-primary' : ''
                    }`}
                  >
                    {badgeCount > 99 ? '99+' : badgeCount}
                  </Chip>
                )}
                {showWarningDot && (
                  <span className="h-2 w-2 rounded-full bg-danger" />
                )}
              </>
            )}
          </NavLink>
        );

        if (isCollapsed) {
          return (
            <Tooltip
              key={item.path}
              content={
                <div className="flex items-center gap-1.5 py-0.5">
                  <span>{item.label}</span>
                  {badgeCount !== null && (
                    <Chip size="sm" color={item.badgeColor || 'primary'} variant="flat" className="h-4 text-[9px] px-1 font-bold">
                      {badgeCount > 99 ? '99+' : badgeCount}
                    </Chip>
                  )}
                </div>
              }
              placement="left"
              closeDelay={0}
            >
              {linkContent}
            </Tooltip>
          );
        }

        return linkContent;
      })}
    </div>
  );

  return (
    <div className="flex h-[100dvh] w-full bg-default-50 text-foreground overflow-hidden">
      {/* Desktop Collapsible Sidebar */}
      <aside
        className={`fixed inset-y-0 start-0 z-50 flex shrink-0 flex-col border-e border-default-200 bg-content1 transition-all duration-300 lg:static lg:z-auto lg:translate-x-0 lg:rtl:translate-x-0 max-lg:rtl:translate-x-full max-lg:ltr:-translate-x-full max-lg:-translate-x-full ${
          isSidebarCollapsed ? 'w-[76px]' : 'w-64'
        }`}
        data-testid="app-sidebar"
      >
        {/* Brand header */}
        <div className={`flex h-16 items-center border-b border-default-200 px-3.5 ${
          isSidebarCollapsed ? 'justify-center' : 'justify-between'
        }`}>
          <div className="flex items-center min-w-0">
            <img
              src="/logo.png"
              alt={ar.app.title}
              className={`w-auto object-contain transition-all ${
                isSidebarCollapsed ? 'h-7 max-w-[48px]' : 'h-9 max-w-[170px]'
              }`}
            />
            <span className="sr-only">{ar.app.title}</span>
          </div>

          <Button
            isIconOnly
            variant="light"
            size="sm"
            className="hidden lg:flex text-default-400 hover:text-default-700 min-w-[44px] min-h-[44px]"
            onPress={toggleSidebarCollapsed}
            aria-label={isSidebarCollapsed ? 'توسيع القائمة' : 'طي القائمة'}
          >
            {isSidebarCollapsed ? (
              <ChevronLeftIcon className="h-4 w-4 rtl:rotate-0 ltr:rotate-180" />
            ) : (
              <ChevronRightIcon className="h-4 w-4 rtl:rotate-0 ltr:rotate-180" />
            )}
          </Button>
        </div>

        {/* Primary Action Button: New Work Order */}
        <div className={`pt-3 pb-2 ${isSidebarCollapsed ? 'px-2 flex justify-center' : 'px-3'}`}>
          {isSidebarCollapsed ? (
            <Tooltip content="أمر شغل جديد" placement="left" closeDelay={0}>
              <Button
                as={Link}
                to="/work-orders/new"
                isIconOnly
                color="primary"
                variant="solid"
                aria-label="أمر شغل جديد"
                className="shadow-none"
              >
                <PlusIcon className="h-5 w-5" />
              </Button>
            </Tooltip>
          ) : (
            <Button
              as={Link}
              to="/work-orders/new"
              color="primary"
              variant="solid"
              className="w-full font-semibold shadow-none"
              startContent={<PlusIcon className="h-5 w-5" />}
            >
              أمر شغل جديد
            </Button>
          )}
        </div>

        {/* Navigation Groups List */}
        <ScrollShadow className="flex-1 px-3 py-2 space-y-3">
          {navGroups.map((group) => renderNavGroup(group, isSidebarCollapsed))}
        </ScrollShadow>

        {/* Footer Area: User dropdown & WhatsApp Provider Chip */}
        <div className="border-t border-default-200 p-3 space-y-2">
          {/* User Dropdown */}
          <Dropdown placement="top-start">
            <DropdownTrigger>
              <button
                type="button"
                className={`flex w-full items-center rounded-xl p-1.5 transition-colors hover:bg-default-100 focus:outline-none ${
                  isSidebarCollapsed ? 'justify-center' : 'gap-2.5 bg-default-50 p-2'
                }`}
                aria-label="حساب المستخدم"
              >
                <Avatar
                  name={user?.name || 'مدير'}
                  size="sm"
                  color="primary"
                  className="font-bold text-xs shrink-0"
                />
                {!isSidebarCollapsed && (
                  <>
                    <div className="flex flex-col min-w-0 flex-1 text-start">
                      <span className="truncate text-xs font-bold text-default-900">
                        {user?.name || 'المدير'}
                      </span>
                      <span className="truncate text-[10px] text-default-400">
                        {user?.email || 'admin'}
                      </span>
                    </div>
                    <ChevronDownIcon className="h-3.5 w-3.5 text-default-400 shrink-0" />
                  </>
                )}
              </button>
            </DropdownTrigger>
            <DropdownMenu aria-label="خيارات الحساب" variant="flat">
              <DropdownItem key="profile" textValue={user?.name} isReadOnly className="h-14 gap-2">
                <p className="font-semibold text-xs text-default-500">تم تسجيل الدخول كـ</p>
                <p className="font-bold text-sm text-default-900">{user?.email}</p>
              </DropdownItem>
              <DropdownItem
                key="change-password"
                startContent={<KeyIcon className="h-4 w-4 text-default-500" />}
                onPress={() => setIsPasswordDialogOpen(true)}
              >
                {ar.auth.changePassword}
              </DropdownItem>
              <DropdownItem
                key="logout"
                color="danger"
                className="text-danger"
                startContent={<ArrowRightOnRectangleIcon className="h-4 w-4 rtl:-scale-x-100" />}
                onPress={() => setIsLogoutDialogOpen(true)}
              >
                {ar.auth.logout}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>

          {/* WhatsApp Provider Mode Chip */}
          {!isSidebarCollapsed ? (
            <div className="flex items-center justify-between px-1 pt-1">
              <span className="text-[10px] text-default-400 font-medium">واتساب:</span>
              <Chip
                size="sm"
                variant="flat"
                color="success"
                className="text-[10px] font-bold h-5 px-1.5"
              >
                {providerStatus?.provider === 'cloud' ? 'سحابي (Cloud)' : 'فاسينجر (Wassenger)'}
              </Chip>
            </div>
          ) : (
            <div className="flex justify-center pt-1">
              <Tooltip
                content={`مزود الواتساب: ${
                  providerStatus?.provider === 'cloud' ? 'سحابي (Cloud)' : 'فاسينجر (Wassenger)'
                }`}
                placement="left"
                closeDelay={0}
              >
                <span className="h-2.5 w-2.5 rounded-full bg-success" />
              </Tooltip>
            </div>
          )}
        </div>
      </aside>

      {/* Mobile Drawer (HeroUI) */}
      <Drawer
        isOpen={isMobileDrawerOpen}
        onOpenChange={setIsMobileDrawerOpen}
        placement="right"
        size="xs"
        classNames={{
          base: 'max-w-[280px]',
        }}
      >
        <DrawerContent className="safe-top safe-bottom">
          {(onClose) => (
            <>
              <DrawerHeader className="flex items-center justify-between border-b border-default-200 px-4 py-3">
                <div className="flex items-center">
                  <img
                    src="/logo.png"
                    alt={ar.app.title}
                    className="h-9 w-auto max-w-[180px] object-contain shrink-0"
                  />
                  <span className="sr-only">{ar.app.title}</span>
                </div>
              </DrawerHeader>

              <DrawerBody className="p-0">
                <div className="px-3 pt-3 pb-2">
                  <Button
                    as={Link}
                    to="/work-orders/new"
                    color="primary"
                    variant="solid"
                    className="w-full font-semibold shadow-none min-h-[44px]"
                    startContent={<PlusIcon className="h-5 w-5" />}
                    onPress={onClose}
                  >
                    أمر شغل جديد
                  </Button>
                </div>

                <ScrollShadow className="flex-1 px-3 py-2 space-y-3">
                  {navGroups.map((group) => renderNavGroup(group, false, onClose))}
                </ScrollShadow>
              </DrawerBody>

              <DrawerFooter className="border-t border-default-200 flex-col p-3 gap-2">
                <div className="flex items-center justify-between w-full px-1">
                  <span className="text-xs text-default-500 font-medium">مزود الواتساب:</span>
                  <Chip
                    size="sm"
                    variant="flat"
                    color="success"
                    className="text-[10px] font-bold h-5"
                  >
                    {providerStatus?.provider === 'cloud' ? 'سحابي (Cloud)' : 'فاسينجر (Wassenger)'}
                  </Chip>
                </div>
              </DrawerFooter>
            </>
          )}
        </DrawerContent>
      </Drawer>

      {/* Main Content Area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Top Navbar */}
        <header className="flex h-16 items-center justify-between border-b border-default-200 bg-content1 ps-3.5 pe-3.5 sm:ps-6 sm:pe-6 safe-top">
          <div className="flex items-center gap-3">
            <Button
              isIconOnly
              variant="light"
              className="lg:hidden min-w-[44px] min-h-[44px] w-11 h-11"
              onPress={() => setIsMobileDrawerOpen(true)}
              aria-label="القائمة الجانبية"
            >
              <Bars3Icon className="h-6 w-6" />
            </Button>
            <span className="text-sm font-semibold text-default-700 hidden sm:inline-block">
              {ar.app.subtitle}
            </span>
          </div>

          {/* Top Navbar User Dropdown */}
          <div className="flex items-center gap-3">
            <Dropdown placement="bottom-end">
              <DropdownTrigger>
                <button
                  type="button"
                  className="flex items-center gap-2 rounded-xl p-1.5 min-h-[44px] transition-colors hover:bg-default-100 focus:outline-none"
                  aria-label="قائمة المستخدم"
                >
                  <Avatar
                    name={user?.name || 'مدير'}
                    size="sm"
                    color="primary"
                    className="font-bold text-xs"
                  />
                  <span className="hidden text-sm font-medium text-default-800 sm:inline-block">
                    {user?.name}
                  </span>
                  <ChevronDownIcon className="h-4 w-4 text-default-400" />
                </button>
              </DropdownTrigger>
              <DropdownMenu aria-label="خيارات الحساب" variant="flat">
                <DropdownItem key="profile" textValue={user?.name} isReadOnly className="h-14 gap-2">
                  <p className="font-semibold text-xs text-default-500">تم تسجيل الدخول كـ</p>
                  <p className="font-bold text-sm text-default-900">{user?.email}</p>
                </DropdownItem>
                <DropdownItem
                  key="change-password"
                  startContent={<KeyIcon className="h-4 w-4 text-default-500" />}
                  onPress={() => setIsPasswordDialogOpen(true)}
                >
                  {ar.auth.changePassword}
                </DropdownItem>
                <DropdownItem
                  key="logout"
                  color="danger"
                  className="text-danger"
                  startContent={<ArrowRightOnRectangleIcon className="h-4 w-4 rtl:-scale-x-100" />}
                  onPress={() => setIsLogoutDialogOpen(true)}
                >
                  {ar.auth.logout}
                </DropdownItem>
              </DropdownMenu>
            </Dropdown>
          </div>
        </header>

        {/* Page body */}
        <main className="flex-1 overflow-y-auto p-3.5 sm:p-6 lg:p-8 safe-bottom">
          <Suspense fallback={<PageSkeleton />}>
            <Outlet />
          </Suspense>
        </main>
      </div>

      {/* Global Toast Container & Realtime WebSocket Notifications */}
      <ToastContainer />
      <RealtimeNotifications />

      {/* Change Password Dialog */}
      <ChangePasswordDialog
        isOpen={isPasswordDialogOpen}
        onClose={() => setIsPasswordDialogOpen(false)}
      />

      {/* Logout Confirm Dialog */}
      <ConfirmDialog
        isOpen={isLogoutDialogOpen}
        onClose={() => setIsLogoutDialogOpen(false)}
        onConfirm={handleLogout}
        title={ar.auth.logout}
        message="هل أنت متأكد من رغبتك في تسجيل الخروج من النظام؟"
        confirmText={ar.auth.logout}
        variant="danger"
        isLoading={isLoggingOut}
      />
    </div>
  );
};

export default AppShell;
