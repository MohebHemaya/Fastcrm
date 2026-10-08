import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, beforeEach } from 'vitest';
import App from '@/App';
import ar from '@/i18n/ar';
import { useAuthStore } from '@/lib/stores/useAuthStore';

describe('App Root Layout and RTL Rendering', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      isAuthenticated: false,
      isLoading: false,
    });
  });

  it('renders application with RTL direction and Arabic title', async () => {
    const { container } = render(<App />);

    // Find element with dir="rtl"
    const rtlContainer = container.querySelector('[dir="rtl"]');
    expect(rtlContainer).toBeInTheDocument();

    // Verify Arabic title in document
    await waitFor(() => {
      const titleElements = screen.getAllByText(ar.app.title);
      expect(titleElements.length).toBeGreaterThan(0);
    });
  });
});
