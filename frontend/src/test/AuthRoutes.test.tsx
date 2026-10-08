import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import ProtectedRoute from '@/components/auth/ProtectedRoute';
import GuestRoute from '@/components/auth/GuestRoute';
import { useAuthStore } from '@/lib/stores/useAuthStore';

describe('AuthRoutes (ProtectedRoute & GuestRoute)', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      isAuthenticated: false,
      isLoading: false,
    });
  });

  describe('ProtectedRoute', () => {
    it('shows full-screen loader while loading auth status', () => {
      useAuthStore.setState({ isLoading: true });

      render(
        <MemoryRouter initialEntries={['/dashboard']}>
          <Routes>
            <Route element={<ProtectedRoute />}>
              <Route path="/dashboard" element={<div>لوحة التحكم المحمية</div>} />
            </Route>
          </Routes>
        </MemoryRouter>
      );

      expect(screen.getByTestId('app-loader')).toBeInTheDocument();
      expect(screen.queryByText('لوحة التحكم المحمية')).not.toBeInTheDocument();
    });

    it('redirects unauthenticated user to /login', () => {
      useAuthStore.setState({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });

      render(
        <MemoryRouter initialEntries={['/dashboard']}>
          <Routes>
            <Route element={<ProtectedRoute />}>
              <Route path="/dashboard" element={<div>لوحة التحكم المحمية</div>} />
            </Route>
            <Route path="/login" element={<div>صفحة تسجيل الدخول</div>} />
          </Routes>
        </MemoryRouter>
      );

      expect(screen.getByText('صفحة تسجيل الدخول')).toBeInTheDocument();
      expect(screen.queryByText('لوحة التحكم المحمية')).not.toBeInTheDocument();
    });

    it('renders child routes when user is authenticated', () => {
      useAuthStore.setState({
        user: { id: 1, name: 'مدير', email: 'admin@example.com' },
        isAuthenticated: true,
        isLoading: false,
      });

      render(
        <MemoryRouter initialEntries={['/dashboard']}>
          <Routes>
            <Route element={<ProtectedRoute />}>
              <Route path="/dashboard" element={<div>لوحة التحكم المحمية</div>} />
            </Route>
          </Routes>
        </MemoryRouter>
      );

      expect(screen.getByText('لوحة التحكم المحمية')).toBeInTheDocument();
    });
  });

  describe('GuestRoute', () => {
    it('redirects authenticated user to home', () => {
      useAuthStore.setState({
        user: { id: 1, name: 'مدير', email: 'admin@example.com' },
        isAuthenticated: true,
        isLoading: false,
      });

      render(
        <MemoryRouter initialEntries={['/login']}>
          <Routes>
            <Route element={<GuestRoute />}>
              <Route path="/login" element={<div>صفحة تسجيل الدخول</div>} />
            </Route>
            <Route path="/" element={<div>الصفحة الرئيسية</div>} />
          </Routes>
        </MemoryRouter>
      );

      expect(screen.getByText('الصفحة الرئيسية')).toBeInTheDocument();
      expect(screen.queryByText('صفحة تسجيل الدخول')).not.toBeInTheDocument();
    });

    it('renders guest route content when user is not authenticated', () => {
      useAuthStore.setState({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });

      render(
        <MemoryRouter initialEntries={['/login']}>
          <Routes>
            <Route element={<GuestRoute />}>
              <Route path="/login" element={<div>صفحة تسجيل الدخول للزائر</div>} />
            </Route>
          </Routes>
        </MemoryRouter>
      );

      expect(screen.getByText('صفحة تسجيل الدخول للزائر')).toBeInTheDocument();
    });
  });
});
