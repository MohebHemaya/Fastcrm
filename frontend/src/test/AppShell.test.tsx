import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import AppShell from '@/components/layout/AppShell';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { ar } from '@/i18n/ar';

describe('AppShell', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: {
        id: 1,
        name: 'أحمد محمود',
        email: 'ahmed@example.com',
      },
      isAuthenticated: true,
      isLoading: false,
    });
  });

  it('renders all required sidebar navigation links in Arabic', () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    // Verify all phase 1 sidebar entries
    expect(screen.getByText(ar.nav.dashboard)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.drivers)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.import)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.questions)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.workOrders)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.reports)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.settings)).toBeInTheDocument();
    expect(screen.getByText(ar.nav.activityLog)).toBeInTheDocument();
  });

  it('renders RTL sidebar container with logical properties', () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    const sidebar = screen.getByTestId('app-sidebar');
    expect(sidebar).toBeInTheDocument();
    expect(sidebar.className).toContain('start-0');
    expect(sidebar.className).toContain('border-e');
    expect(sidebar.className).toContain('lg:static');
    expect(sidebar.className).toContain('lg:translate-x-0');
    expect(sidebar.className).toContain('lg:rtl:translate-x-0');
    expect(sidebar.className).toContain('max-lg:rtl:translate-x-full');
  });

  it('displays user name and email in the shell', () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    expect(screen.getAllByText('أحمد محمود').length).toBeGreaterThan(0);
    expect(screen.getByText('ahmed@example.com')).toBeInTheDocument();
  });
});
