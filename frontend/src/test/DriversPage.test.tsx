import { describe, it, expect } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import DriversPage from '@/routes/DriversPage';
import { ar } from '@/i18n/ar';

const renderDriversPage = () => {
  return render(
    <HeroUIProvider locale="ar-EG">
      <MemoryRouter>
        <DriversPage />
      </MemoryRouter>
    </HeroUIProvider>
  );
};

describe('DriversPage', () => {
  it('renders page header and driver action buttons', async () => {
    renderDriversPage();

    expect(screen.getByRole('heading', { level: 1, name: ar.drivers.title })).toBeInTheDocument();
    expect(screen.getByText(ar.drivers.addDriver)).toBeInTheDocument();
    expect(screen.getByText(ar.drivers.importDrivers)).toBeInTheDocument();
  });

  it('loads and displays drivers list from API', async () => {
    renderDriversPage();

    await waitFor(() => {
      expect(screen.getByText('+201012345678')).toBeInTheDocument();
      expect(screen.getByText('أحمد محمود')).toBeInTheDocument();
      expect(screen.getByText('+201198765432')).toBeInTheDocument();
      expect(screen.getByText('محمد علي')).toBeInTheDocument();
    });
  });

  it('filters drivers when typing in search input', async () => {
    const user = userEvent.setup();
    renderDriversPage();

    await waitFor(() => {
      expect(screen.getByText('+201012345678')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText(ar.drivers.searchPlaceholder);
    await user.type(searchInput, 'أحمد');

    await waitFor(() => {
      expect(screen.getByText('أحمد محمود')).toBeInTheDocument();
      expect(screen.queryByText('محمد علي')).not.toBeInTheDocument();
    });
  });

  it('opens add driver modal when clicking add driver button', async () => {
    const user = userEvent.setup();
    renderDriversPage();

    const addButton = screen.getByRole('button', { name: new RegExp(ar.drivers.addDriver) });
    await user.click(addButton);

    await waitFor(() => {
      expect(screen.getAllByText(ar.drivers.addDriver).length).toBeGreaterThan(1);
      expect(screen.getByPlaceholderText('مثال: 01012345678 أو +201012345678')).toBeInTheDocument();
    });
  });

  it('opens driver detail drawer when clicking view button', async () => {
    const user = userEvent.setup();
    renderDriversPage();

    await waitFor(() => {
      expect(screen.getByText('+201012345678')).toBeInTheDocument();
    });

    const viewButtons = screen.getAllByTitle(ar.drivers.driverDetails);
    await user.click(viewButtons[0]);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: 'أحمد محمود' })).toBeInTheDocument();
      expect(screen.getByText(ar.drivers.statusHistory)).toBeInTheDocument();
      expect(screen.getByText(ar.drivers.changeStatus)).toBeInTheDocument();
    });
  });
});
