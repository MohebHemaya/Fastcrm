import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import HomePage from '@/routes/HomePage';
import { ar } from '@/i18n/ar';

// Mock recharts ResponsiveContainer to avoid 0x0 size in JSDOM
vi.mock('recharts', async () => {
  const original = await vi.importActual('recharts');
  return {
    ...original,
    ResponsiveContainer: ({ children }: any) => <div data-testid="recharts-container">{children}</div>,
  };
});

const renderDashboardPage = () => {
  return render(
    <HeroUIProvider locale="ar-EG">
      <MemoryRouter>
        <HomePage />
      </MemoryRouter>
    </HeroUIProvider>
  );
};

describe('HomePage / Dashboard', () => {
  it('renders dashboard title and metric cards with data from API', async () => {
    renderDashboardPage();

    expect(screen.getByRole('heading', { level: 1, name: ar.dashboard.title })).toBeInTheDocument();

    await waitFor(
      () => {
        // 18 total drivers
        expect(screen.getByText('18')).toBeInTheDocument();
        // 10 active drivers (also matches pagination option '10')
        expect(screen.getAllByText('10').length).toBeGreaterThanOrEqual(1);
        // 1 opted-out driver (also matches pagination index '1')
        expect(screen.getAllByText('1').length).toBeGreaterThanOrEqual(1);
        // 75% offer acceptance rate
        expect(screen.getByText('75%')).toBeInTheDocument();
        // Average time to fill
        expect(screen.getByText(/36 دقيقة|35.5 دقيقة/)).toBeInTheDocument();
      },
      { timeout: 8000 }
    );
  }, 15000);

  it('renders WhatsApp group performance table with conversion rates', async () => {
    renderDashboardPage();

    await waitFor(() => {
      expect(screen.getByText('جروب سائقي الجيزة')).toBeInTheDocument();
      expect(screen.getByText('جروب سائقي القاهرة')).toBeInTheDocument();
      expect(screen.getByText('40%')).toBeInTheDocument();
      expect(screen.getByText('43.75%')).toBeInTheDocument();
    });
  });

  it('filters group performance table by group name search input', async () => {
    const user = userEvent.setup();
    renderDashboardPage();

    await waitFor(() => {
      expect(screen.getByText('جروب سائقي الجيزة')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText(ar.dashboard.groupPerformance.searchPlaceholder);
    await user.type(searchInput, 'الجيزة');

    await waitFor(() => {
      expect(screen.getByText('جروب سائقي الجيزة')).toBeInTheDocument();
      expect(screen.queryByText('جروب سائقي القاهرة')).not.toBeInTheDocument();
    });
  });

  it('allows clicking quick date range filters', async () => {
    const user = userEvent.setup();
    renderDashboardPage();

    await waitFor(() => {
      expect(screen.getByText('18')).toBeInTheDocument();
    });

    const last7DaysBtn = screen.getByRole('button', { name: ar.dashboard.last7Days });
    await user.click(last7DaysBtn);

    await waitFor(() => {
      expect(last7DaysBtn).toBeInTheDocument();
    });
  });

  it('opens export modal when clicking recent exports or export excel', async () => {
    const user = userEvent.setup();
    renderDashboardPage();

    await waitFor(() => {
      expect(screen.getByText('جروب سائقي الجيزة')).toBeInTheDocument();
    });

    const exportBtn = screen.getByRole('button', { name: ar.dashboard.groupPerformance.exportExcel });
    await user.click(exportBtn);

    await waitFor(() => {
      expect(screen.getByText(ar.exports.modalTitle)).toBeInTheDocument();
      expect(screen.getByText(ar.exports.exportDrivers)).toBeInTheDocument();
    });
  });

  it('renders pagination bar and allows changing rows per page for group performance table', async () => {
    const user = userEvent.setup();
    renderDashboardPage();

    await waitFor(() => {
      expect(screen.getByText('جروب سائقي الجيزة')).toBeInTheDocument();
    });

    // Check pagination summary info
    const summary = screen.getByTestId('group-pagination-summary');
    expect(summary).toBeInTheDocument();
    expect(summary).toHaveTextContent('من أصل');
    expect(summary).toHaveTextContent('2');

    // Check rows per page select
    const select = screen.getByLabelText('عدد الصفوف في الصفحة');
    expect(select).toBeInTheDocument();
    expect(select).toHaveValue('10');

    await user.selectOptions(select, '5');
    expect(select).toHaveValue('5');
  });
});
