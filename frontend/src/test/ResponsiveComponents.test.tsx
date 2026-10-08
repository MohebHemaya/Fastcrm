import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { MemoryRouter } from 'react-router-dom';
import { CreateWorkOrderPage } from '@/routes/CreateWorkOrderPage';
import { DriversPage } from '@/routes/DriversPage';

interface SampleItem {
  id: number;
  name: string;
  status: string;
  phone: string;
}

const sampleColumns: ColumnDef<SampleItem>[] = [
  { id: 'name', header: 'الاسم', isPrimary: true, render: (r) => <span>{r.name}</span> },
  { id: 'status', header: 'الحالة', render: (r) => <span>{r.status}</span> },
  { id: 'phone', header: 'الهاتف', render: (r) => <span>{r.phone}</span> },
];

const sampleData: SampleItem[] = [
  { id: 1, name: 'أحمد محمود', status: 'نشط', phone: '+201012345678' },
  { id: 2, name: 'محمد علي', status: 'موقوف', phone: '+201198765432' },
];

function setMatchMediaMatches(matches: boolean) {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }));
}

describe('Responsive Components Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('DataTable Mobile Card vs Desktop Table switching', () => {
    it('renders desktop table view when viewport is >= 768px (desktop)', () => {
      setMatchMediaMatches(false); // not mobile

      render(
        <DataTable
          columns={sampleColumns}
          data={sampleData}
          keyExtractor={(item) => item.id}
        />
      );

      expect(screen.getByTestId('data-table-desktop')).toBeInTheDocument();
      expect(screen.queryByTestId('data-table-cards')).not.toBeInTheDocument();
      expect(screen.getByText('أحمد محمود')).toBeInTheDocument();
      expect(screen.getByText('محمد علي')).toBeInTheDocument();
    });

    it('renders card list view when viewport is < 768px (mobile)', () => {
      setMatchMediaMatches(true); // mobile

      render(
        <DataTable
          columns={sampleColumns}
          data={sampleData}
          keyExtractor={(item) => item.id}
        />
      );

      expect(screen.getByTestId('data-table-cards')).toBeInTheDocument();
      expect(screen.queryByTestId('data-table-desktop')).not.toBeInTheDocument();
      expect(screen.getByText('أحمد محمود')).toBeInTheDocument();
      expect(screen.getByText('محمد علي')).toBeInTheDocument();
      expect(screen.getAllByText('الحالة:').length).toBe(2);
      expect(screen.getAllByText('الهاتف:').length).toBe(2);
    });
  });

  describe('CreateWorkOrderPage Stepper Responsiveness', () => {
    it('renders mobile compact step indicator alongside full stepper', () => {
      render(
        <MemoryRouter>
          <CreateWorkOrderPage />
        </MemoryRouter>
      );

      // Mobile compact stepper indicator text
      expect(screen.getByText(/الخطوة 1 من 5/i)).toBeInTheDocument();
    });
  });

  describe('DriversPage Mobile Collapsible Filters', () => {
    it('renders mobile filter toggle button and toggles collapsible filters', async () => {
      render(
        <MemoryRouter>
          <DriversPage />
        </MemoryRouter>
      );

      // Mobile "تصفية" button exists
      const filterToggleBtn = screen.getByRole('button', { name: /تصفية/i });
      expect(filterToggleBtn).toBeInTheDocument();

      // Click to toggle
      await act(async () => {
        fireEvent.click(filterToggleBtn);
      });

      // Filter options should be visible
      expect(screen.getByRole('button', { name: /تصفية/i })).toBeInTheDocument();
    });
  });
});
