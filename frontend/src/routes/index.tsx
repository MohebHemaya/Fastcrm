import { createBrowserRouter } from 'react-router-dom';
import AppShell from '@/components/layout/AppShell';
import ProtectedRoute from '@/components/auth/ProtectedRoute';
import GuestRoute from '@/components/auth/GuestRoute';
import LoginPage from './LoginPage';
import HomePage from './HomePage';
import DriversPage from './DriversPage';
import NewDriversPage from './NewDriversPage';
import ImportWizardPage from './ImportWizardPage';
import ActivityLogsPage from './ActivityLogsPage';
import WhatsAppMessagesPage from './WhatsAppMessagesPage';
import SettingsPage from '@/features/settings/SettingsPage';
import WorkOrdersListPage from './WorkOrdersListPage';
import CreateWorkOrderPage from './CreateWorkOrderPage';
import WorkOrderDetailPage from './WorkOrderDetailPage';
import ReportsPage from './ReportsPage';
import HealthPage from './HealthPage';
import UsersPage from './UsersPage';
import NotFoundPage from './NotFoundPage';
import RouteErrorBoundary from '@/components/ui/RouteErrorBoundary';

export const router = createBrowserRouter([
  // Guest-only routes
  {
    element: <GuestRoute />,
    errorElement: <RouteErrorBoundary />,
    children: [
      {
        path: '/login',
        element: <LoginPage />,
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
    element: <NotFoundPage />,
  },
]);

export default router;
