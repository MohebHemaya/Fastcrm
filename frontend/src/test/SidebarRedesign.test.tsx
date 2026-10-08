import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import AppShell from '@/components/layout/AppShell';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { useUiStore } from '@/lib/stores/useUiStore';

describe('Sidebar Redesign & Badges', () => {
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
    useUiStore.setState({
      isSidebarCollapsed: false,
    });
  });

  it('renders primary action button linking to /work-orders/new', () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    const newOrderButtons = screen.getAllByRole('button', { name: /أمر شغل جديد/i });
    expect(newOrderButtons.length).toBeGreaterThan(0);
    const link = newOrderButtons[0].closest('a');
    expect(link).toHaveAttribute('href', '/work-orders/new');
  });

  it('renders the 5 semantic group headers', () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    expect(screen.getByText('التشغيل اليومي')).toBeInTheDocument();
    expect(screen.getByText('التواصل')).toBeInTheDocument();
    expect(screen.getByText('التحليلات')).toBeInTheDocument();
    expect(screen.getByText('النظام')).toBeInTheDocument();
  });

  it('renders WhatsApp provider status chip in footer', async () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<AppShell />}>
            <Route path="/" element={<div>محتوى تجريبي</div>} />
          </Route>
        </Routes>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/وضع تجريبي \(Fake\)/i)).toBeInTheDocument();
    });
  });

  it('toggles collapsible sidebar mode and adjusts width', () => {
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
    expect(sidebar.className).toContain('w-64');

    const toggleButton = screen.getByLabelText(/طي القائمة/i);
    fireEvent.click(toggleButton);

    expect(useUiStore.getState().isSidebarCollapsed).toBe(true);
    expect(sidebar.className).toContain('w-[76px]');
  });
});
