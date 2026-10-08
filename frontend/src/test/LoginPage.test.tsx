import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from '@/routes/LoginPage';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { ar } from '@/i18n/ar';

describe('LoginPage', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      isAuthenticated: false,
      isLoading: false,
    });
  });

  it('renders login form with Arabic labels and title', () => {
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(ar.app.title);
    expect(screen.getByText(ar.auth.email)).toBeInTheDocument();
    expect(screen.getByText(ar.auth.password)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: ar.auth.login })).toBeInTheDocument();
  });

  it('validates required fields on empty submit', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    await user.click(screen.getByRole('button', { name: ar.auth.login }));

    await waitFor(() => {
      expect(screen.getByText(ar.auth.emailRequired)).toBeInTheDocument();
      expect(screen.getByText(ar.auth.passwordRequired)).toBeInTheDocument();
    });
  });

  it('validates invalid email format', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    const emailInput = screen.getByPlaceholderText('admin@example.com');
    const passwordInput = screen.getByPlaceholderText('••••••••');
    await user.type(emailInput, 'notanemail');
    await user.type(passwordInput, 'password123');
    await user.click(screen.getByRole('button', { name: ar.auth.login }));

    await waitFor(() => {
      expect(screen.getByText(ar.auth.emailInvalid)).toBeInTheDocument();
    });
  });

  it('maps server 422 validation errors to fields', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    const emailInput = screen.getByPlaceholderText('admin@example.com');
    const passwordInput = screen.getByPlaceholderText('••••••••');

    await user.type(emailInput, 'admin@example.com');
    await user.type(passwordInput, 'wrongpassword');
    await user.click(screen.getByRole('button', { name: ar.auth.login }));

    await waitFor(() => {
      expect(
        screen.getAllByText('البريد الإلكتروني أو كلمة المرور غير صحيحة.').length
      ).toBeGreaterThanOrEqual(1);
    });
  });

  it('displays 403 inactive account error in general alert banner', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    const emailInput = screen.getByPlaceholderText('admin@example.com');
    const passwordInput = screen.getByPlaceholderText('••••••••');

    await user.type(emailInput, 'inactive@example.com');
    await user.type(passwordInput, 'password123');
    await user.click(screen.getByRole('button', { name: ar.auth.login }));

    await waitFor(() => {
      expect(
        screen.getByText('الحساب معطل، يرجى التواصل مع الإدارة.')
      ).toBeInTheDocument();
    });
  });

  it('successfully logs in and updates auth store', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );

    const emailInput = screen.getByPlaceholderText('admin@example.com');
    const passwordInput = screen.getByPlaceholderText('••••••••');

    await user.type(emailInput, 'admin@example.com');
    await user.type(passwordInput, 'password123');
    await user.click(screen.getByRole('button', { name: ar.auth.login }));

    await waitFor(() => {
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user?.email).toBe('admin@example.com');
    });
  });
});
