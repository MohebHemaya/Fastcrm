import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import { BrowserRouter } from 'react-router-dom';
import UsersPage from '@/routes/UsersPage';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { ar } from '@/i18n/ar';

const renderComponent = () => {
  return render(
    <HeroUIProvider>
      <BrowserRouter>
        <UsersPage />
      </BrowserRouter>
    </HeroUIProvider>
  );
};

describe('UsersPage Frontend Suite', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: {
        id: 1,
        name: 'المدير العام',
        email: 'admin@fastcrm.local',
      },
      isAuthenticated: true,
    });
  });

  it('renders page header, KPI cards, and user list table', async () => {
    renderComponent();

    // Check title and add button
    expect(screen.getByRole('heading', { name: ar.users.title })).toBeInTheDocument();
    expect(screen.getByText(ar.users.addUser)).toBeInTheDocument();

    // Wait for users to load
    await waitFor(() => {
      expect(screen.getByText('المدير العام')).toBeInTheDocument();
      expect(screen.getByText('مشرف العمليات')).toBeInTheDocument();
      expect(screen.getByText('مستخدم معطل')).toBeInTheDocument();
    });

    // Check KPI cards
    expect(screen.getByText(ar.users.totalUsers)).toBeInTheDocument();
    expect(screen.getByText(ar.users.activeUsers)).toBeInTheDocument();
    expect(screen.getByText(ar.users.inactiveUsers)).toBeInTheDocument();

    // Check current user badge
    expect(screen.getByText(ar.users.currentUser)).toBeInTheDocument();
  });

  it('filters users by search input', async () => {
    renderComponent();

    await waitFor(() => {
      expect(screen.getByText('المدير العام')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText(ar.users.searchPlaceholder);
    await userEvent.type(searchInput, 'مشرف');

    await waitFor(() => {
      expect(searchInput).toHaveValue('مشرف');
    });
  });

  it('opens add user modal and validates required fields', async () => {
    renderComponent();

    const addBtn = screen.getByText(ar.users.addUser);
    fireEvent.click(addBtn);

    // Modal should open
    await waitFor(() => {
      expect(screen.getAllByText(ar.users.addUser).length).toBeGreaterThan(1);
    });

    // Submit without input should trigger validation
    const submitBtn = screen.getByRole('button', { name: ar.common.add });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText('اسم المستخدم مطلوب.')).toBeInTheDocument();
    });
  });

  it('allows filling form and submitting a new user', async () => {
    renderComponent();

    const addBtn = screen.getByText(ar.users.addUser);
    fireEvent.click(addBtn);

    await waitFor(() => {
      expect(screen.getByPlaceholderText(ar.users.namePlaceholder)).toBeInTheDocument();
    });

    const nameInput = screen.getByPlaceholderText(ar.users.namePlaceholder);
    const emailInput = screen.getByPlaceholderText(ar.users.emailPlaceholder);
    const passwordInput = screen.getByPlaceholderText(ar.users.passwordPlaceholder);

    await userEvent.type(nameInput, 'أحمد محمود');
    await userEvent.type(emailInput, 'ahmed@fastcrm.local');
    await userEvent.type(passwordInput, 'Password123!');

    const submitBtn = screen.getByRole('button', { name: ar.common.add });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      // Modal should close
      expect(screen.queryByPlaceholderText(ar.users.namePlaceholder)).not.toBeInTheDocument();
    });
  });

  it('opens delete confirm dialog when clicking delete on another user', async () => {
    renderComponent();

    await waitFor(() => {
      expect(screen.getByText('مشرف العمليات')).toBeInTheDocument();
    });

    // Delete buttons
    const deleteButtons = screen.getAllByRole('button', { name: ar.users.deleteUser });
    // First button belongs to admin (id: 1) and should be disabled
    expect(deleteButtons[0]).toBeDisabled();

    // Second button belongs to operator (id: 2) and should be enabled
    expect(deleteButtons[1]).not.toBeDisabled();
    fireEvent.click(deleteButtons[1]);

    // Confirmation dialog should open
    await waitFor(() => {
      expect(screen.getByText(ar.users.deleteConfirmTitle)).toBeInTheDocument();
    });

    // Confirm delete
    const confirmBtn = screen.getByRole('button', { name: ar.common.delete });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(screen.queryByText(ar.users.deleteConfirmTitle)).not.toBeInTheDocument();
    });
  });
});
