import { Suspense, lazy } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import AppShell from '@/components/layout/AppShell';
import ProtectedRoute from '@/components/auth/ProtectedRoute';
import GuestRoute from '@/components/auth/GuestRoute';
import RouteErrorBoundary from '@/components/ui/RouteErrorBoundary';
import PageSkeleton from '@/components/ui/PageSkeleton';
import AppLoader from '@/components/ui/AppLoader';

// Code-split route components to minimize initial bundle size and maximize load speed
const LoginPage = lazy(() => import('./LoginPage'));
const HomePage = lazy(() => import('./HomePage'));
const DriversPage = lazy(() => import('./DriversPage'));
const NewDriversPage = lazy(() => import('./NewDriversPage'));
const ImportWizardPage = lazy(() => import('./ImportWizardPage'));
const ActivityLogsPage = lazy(() => import('./ActivityLogsPage'));
const WhatsAppMessagesPage = lazy(() => import('./WhatsAppMessagesPage'));
const SettingsPage = lazy(() => import('@/features/settings/SettingsPage'));
const WorkOrdersListPage = lazy(() => import('./WorkOrdersListPage'));
const CreateWorkOrderPage = lazy(() => import('./CreateWorkOrderPage'));
const WorkOrderDetailPage = lazy(() => import('./WorkOrderDetailPage'));
const ReportsPage = lazy(() => import('./ReportsPage'));
const HealthPage = lazy(() => import('./HealthPage'));
const UsersPage = lazy(() => import('./UsersPage'));
const NotFoundPage = lazy(() => import('./NotFoundPage'));

export const router = createBrowserRouter([
  // Guest-only routes
  {
    element: <GuestRoute />,
    errorElement: <RouteErrorBoundary />,
    children: [
      {
        path: '/login',
        element: (
          <Suspense fallback={<AppLoader />}>
            <LoginPage />
          </Suspense>
        ),
      },
    ],
  },

  // Protected application routes
  {
    element: <ProtectedRoute />,
    errorElement: <RouteErrorBoundary />,
    children: [
      {
        element: <AppShell />,
        errorElement: <RouteErrorBoundary />,
        children: [
          {
            path: '/',
            element: <HomePage />,
          },
          {
            path: '/drivers',
            element: <DriversPage />,
          },
          {
            path: '/new-drivers',
            element: <NewDriversPage />,
          },
          {
            path: '/messages',
            element: <WhatsAppMessagesPage />,
          },
          {
            path: '/import',
            element: <ImportWizardPage />,
          },
          {
            path: '/questions',
            element: <SettingsPage />,
          },
          {
            path: '/work-orders',
            element: <WorkOrdersListPage />,
          },
          {
            path: '/work-orders/create',
            element: <CreateWorkOrderPage />,
          },
          {
            path: '/work-orders/new',
            element: <CreateWorkOrderPage />,
          },
          {
            path: '/work-orders/:id',
            element: <WorkOrderDetailPage />,
          },
          {
            path: '/reports',
            element: <ReportsPage />,
          },
          {
            path: '/settings',
            element: <SettingsPage />,
          },
          {
            path: '/users',
            element: <UsersPage />,
          },
          {
            path: '/activity-logs',
            element: <ActivityLogsPage />,
          },
          {
            path: '/health',
            element: <HealthPage />,
          },
        ],
      },
    ],
  },

  // Fallback 404 route
  {
    path: '*',
    element: (
      <Suspense fallback={<PageSkeleton />}>
        <NotFoundPage />
      </Suspense>
    ),
  },
]);

export default router;
