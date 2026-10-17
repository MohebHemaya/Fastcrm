import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import WorkOrdersListPage from '@/routes/WorkOrdersListPage';
import CreateWorkOrderPage from '@/routes/CreateWorkOrderPage';
import WorkOrderDetailPage from '@/routes/WorkOrderDetailPage';
import { workOrderService } from '@/services/workOrderService';
import { questionService } from '@/services/questionService';
import { ar } from '@/i18n/ar';

vi.mock('@/services/workOrderService', () => ({
  workOrderService: {
    getWorkOrders: vi.fn(),
    getWorkOrder: vi.fn(),
    createDraft: vi.fn(),
    updateDraft: vi.fn(),
    sendOrder: vi.fn(),
    cancelOrder: vi.fn(),
    duplicateOrder: vi.fn(),
    getRecipients: vi.fn(),
    retryRecipient: vi.fn(),
    previewMatch: vi.fn(),
  },
}));

vi.mock('@/services/questionService', () => ({
  questionService: {
    getQuestions: vi.fn(),
  },
}));

const mockWorkOrders = [
  {
    id: 1,
    code: 'WO-0001',
    from_location: 'العاشر من رمضان',
    to_location: 'ميناء الإسكندرية',
    scheduled_at: '2026-10-05T08:00:00Z',
    cargo_type: 'بضائع عامة',
    pay_amount: 1500,
    notes: 'تحميل مباشر',
    trips_needed: 10,
    backup_needed: 5,
    status: 'filling' as const,
    status_label: 'قيد الاستقبال',
    confirmed_count: 7,
    backup_count: 3,
    sent_count: 30,
    filled_at: null,
    send_strategy: 'all' as const,
    batch_size: null,
    batch_interval_minutes: null,
    exclude_same_day_confirmed: true,
    created_at: '2026-10-01T10:00:00Z',
  },
];

const mockOrderDetail = {
  id: 1,
  code: 'WO-0001',
  from_location: 'العاشر من رمضان',
  to_location: 'ميناء الإسكندرية',
  scheduled_at: '2026-10-05T08:00:00Z',
  cargo_type: 'بضائع عامة',
  pay_amount: 1500,
  notes: 'ملاحظات هامة للتحميل',
  trips_needed: 10,
  backup_needed: 5,
  status: 'filling' as const,
  status_label: 'قيد الاستقبال',
  confirmed_count: 7,
  backup_count: 3,
  sent_count: 30,
  filled_at: null,
  send_strategy: 'all' as const,
  batch_size: null,
  batch_interval_minutes: null,
  exclude_same_day_confirmed: true,
  created_at: '2026-10-01T10:00:00Z',
};

const mockRecipients = [
  {
    id: 101,
    work_order_id: 1,
    driver_id: 1,
    driver_name: 'أحمد محمود',
    driver_phone: '+201012345678',
    status: 'confirmed' as const,
    status_label: 'مؤكد',
    sequence_number: 1,
    stage_batch: 1,
    sent_at: '2026-10-01T10:05:00Z',
    responded_at: '2026-10-01T10:06:00Z',
  },
];

describe('Work Orders Frontend Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.mocked(workOrderService.getWorkOrders).mockResolvedValue({
      data: mockWorkOrders,
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      message: 'ok',
    });

    vi.mocked(workOrderService.getWorkOrder).mockResolvedValue({
      data: mockOrderDetail,
      meta: {
        recipient_counts: {
          all: 30,
          confirmed: 7,
          backup: 3,
          declined: 2,
          sent: 18,
          too_late: 0,
          failed: 0,
          cancelled: 0,
        },
      },
      message: 'ok',
    });

    vi.mocked(workOrderService.getRecipients).mockResolvedValue({
      data: mockRecipients,
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 },
      message: 'ok',
    });

    vi.mocked(questionService.getQuestions).mockResolvedValue([
      {
        id: 1,
        key: 'vehicle_type',
        type: 'single_choice',
        type_label: 'اختيار من متعدد',
        text_ar: 'نوع المركبة',
        version: 1,
        sort_order: 1,
        is_active: true,
        is_required: true,
        is_filter: true,
        options: [{ value: 'jumbo', label_ar: 'جامبو' }],
        options_count: 1,
        answers_count: 50,
        versions_count: 1,
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T10:00:00Z',
      },
    ]);

    vi.mocked(workOrderService.previewMatch).mockResolvedValue({
      data: {
        count: 24,
        drivers: [{ id: 1, name: 'أحمد محمود', phone: '+201012345678', status: 'active' }],
      },
      meta: null,
      message: 'ok',
    });
  });

  describe('WorkOrdersListPage', () => {
    it('renders list page with code, locations, and triggers duplication', async () => {
      vi.mocked(workOrderService.duplicateOrder).mockResolvedValueOnce({
        data: { ...mockOrderDetail, id: 2, code: 'WO-0002', status: 'draft' },
        meta: null,
        message: 'duplicated',
      });

      render(
        <HeroUIProvider>
          <MemoryRouter>
            <WorkOrdersListPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      await waitFor(() => {
        expect(screen.getByText('WO-0001')).toBeInTheDocument();
        expect(screen.getByText('العاشر من رمضان')).toBeInTheDocument();
        expect(screen.getByText('ميناء الإسكندرية')).toBeInTheDocument();
      });

      // Find and click duplicate button
      const duplicateBtn = screen.getByTitle(ar.workOrders.duplicateOrder);
      fireEvent.click(duplicateBtn);

      await waitFor(() => {
        expect(workOrderService.duplicateOrder).toHaveBeenCalledWith(1);
      });
    });
  });

  describe('CreateWorkOrderPage', () => {
    it('renders step 1 details and advances to capacity step', async () => {
      render(
        <HeroUIProvider>
          <MemoryRouter>
            <CreateWorkOrderPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      expect(screen.getAllByText(ar.workOrders.steps.details)[0]).toBeInTheDocument();

      // Click Next to proceed to step 2
      const nextBtn = screen.getByText(ar.common.next);
      fireEvent.click(nextBtn);

      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.capacity)[0]).toBeInTheDocument();
      });
    });

    it('advances steps and fetches live matching count in filters step', async () => {
      render(
        <HeroUIProvider>
          <MemoryRouter>
            <CreateWorkOrderPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      // Step 1: Proceed to Step 2
      fireEvent.click(screen.getByText(ar.common.next));

      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.capacity)[0]).toBeInTheDocument();
      });

      // Step 2 is pre-filled with trips_needed=1, proceed to Step 3
      fireEvent.click(screen.getByText(ar.common.next));

      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.filters)[0]).toBeInTheDocument();
      });

      // Wait for debounced previewMatch call
      await waitFor(() => {
        expect(workOrderService.previewMatch).toHaveBeenCalled();
        expect(screen.getByText(/24/)).toBeInTheDocument();
      });
    });

    it('shows confirmation modals on step 5 for saving draft and broadcasting', async () => {
      render(
        <HeroUIProvider>
          <MemoryRouter>
            <CreateWorkOrderPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      // Advance Step 1 -> 2
      fireEvent.click(screen.getByText(ar.common.next));
      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.capacity)[0]).toBeInTheDocument();
      });

      // Advance Step 2 -> 3
      fireEvent.click(screen.getByText(ar.common.next));
      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.filters)[0]).toBeInTheDocument();
      });

      // Advance Step 3 -> 4
      fireEvent.click(screen.getByText(ar.common.next));
      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.strategy)[0]).toBeInTheDocument();
      });

      // Advance Step 4 -> 5
      fireEvent.click(screen.getByText(ar.common.next));
      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.steps.review)[0]).toBeInTheDocument();
      });

      // On Step 5: Click Save Draft -> Confirm Dialog should open
      const saveDraftBtn = screen.getByText(ar.workOrders.saveDraft);
      fireEvent.click(saveDraftBtn);

      await waitFor(() => {
        expect(screen.getByText(ar.workOrders.confirmDraftTitle)).toBeInTheDocument();
      });

      // Close the modal by clicking cancel
      const cancelDraftModalBtn = screen.getByRole('button', { name: ar.common.cancel });
      fireEvent.click(cancelDraftModalBtn);

      // Now click Send / Broadcast -> Confirm Broadcast Dialog should open
      const sendOrderBtn = screen.getByText(ar.workOrders.sendOrder);
      fireEvent.click(sendOrderBtn);

      await waitFor(() => {
        expect(screen.getByText(ar.workOrders.confirmBroadcastTitle)).toBeInTheDocument();
        expect(screen.getByText(ar.workOrders.confirmBroadcastWarning)).toBeInTheDocument();
      });
    });

    it('shows confirmation dialog when clicking discard draft in restored banner', async () => {
      // Simulate draft in localStorage
      localStorage.setItem(
        'fast_crm_work_order_draft',
        JSON.stringify({
          from_governorate: 'القاهرة',
          notes: 'ملاحظات مسودة تجريبية',
        })
      );

      render(
        <HeroUIProvider>
          <MemoryRouter>
            <CreateWorkOrderPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      await waitFor(() => {
        expect(screen.getByText('مسح المسودة والبدء من جديد')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('مسح المسودة والبدء من جديد'));

      await waitFor(() => {
        expect(screen.getByText(ar.workOrders.confirmDiscardTitle)).toBeInTheDocument();
        expect(screen.getByText(ar.workOrders.confirmDiscardPrompt)).toBeInTheDocument();
      });

      localStorage.removeItem('fast_crm_work_order_draft');
    });
  });

  describe('WorkOrderDetailPage', () => {
    it('renders order detail, tabs, counters, and handles cancel order modal', async () => {
      const user = userEvent.setup();

      vi.mocked(workOrderService.cancelOrder).mockResolvedValueOnce({
        data: { ...mockOrderDetail, status: 'cancelled' },
        meta: null,
        message: 'cancelled',
      });

      render(
        <HeroUIProvider>
          <MemoryRouter initialEntries={['/work-orders/1']}>
            <Routes>
              <Route path="/work-orders/:id" element={<WorkOrderDetailPage />} />
            </Routes>
          </MemoryRouter>
        </HeroUIProvider>
      );

      // Check header and stats
      await waitFor(() => {
        expect(screen.getAllByText(/WO-0001/)[0]).toBeInTheDocument();
        expect(screen.getByText('7 / 10')).toBeInTheDocument(); // Confirmed counter
        expect(screen.getByText('3 / 5')).toBeInTheDocument(); // Backup counter
      });

      // Check tabs presence
      expect(screen.getByText(ar.workOrders.tabs.confirmed)).toBeInTheDocument();
      expect(screen.getByText(ar.workOrders.tabs.backup)).toBeInTheDocument();
      expect(screen.getByText(ar.workOrders.tabs.declined)).toBeInTheDocument();
      expect(screen.getByText(ar.workOrders.tabs.sent)).toBeInTheDocument();
      expect(screen.getByText(ar.workOrders.tabs.too_late)).toBeInTheDocument();

      // Open Cancel Dialog
      const cancelBtn = screen.getByRole('button', { name: new RegExp(ar.workOrders.cancelOrder) });
      await user.click(cancelBtn);

      await waitFor(() => {
        expect(screen.getByText(ar.workOrders.cancelModal.message)).toBeInTheDocument();
        expect(screen.getAllByText(ar.workOrders.cancelModal.title)[0]).toBeInTheDocument();
      });

      // Fill reason and confirm
      const reasonInput = screen.getByPlaceholderText(ar.workOrders.cancelModal.reasonPlaceholder);
      await user.type(reasonInput, 'إلغاء لظروف خاصة');

      const confirmCancelBtn = screen.getByRole('button', {
        name: new RegExp(ar.workOrders.cancelModal.confirmButton),
      });
      await user.click(confirmCancelBtn);

      await waitFor(() => {
        expect(workOrderService.cancelOrder).toHaveBeenCalledWith(1, 'إلغاء لظروف خاصة');
      });
    });

    it('polls order data periodically when tab is visible', async () => {
      vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });

      render(
        <HeroUIProvider>
          <MemoryRouter initialEntries={['/work-orders/1']}>
            <Routes>
              <Route path="/work-orders/:id" element={<WorkOrderDetailPage />} />
            </Routes>
          </MemoryRouter>
        </HeroUIProvider>
      );

      // Initial call count
      expect(workOrderService.getWorkOrder).toHaveBeenCalledTimes(1);

      // Fast forward 10 seconds
      act(() => {
        vi.advanceTimersByTime(10000);
      });

      expect(workOrderService.getWorkOrder).toHaveBeenCalledTimes(2);

      // Fast forward another 10 seconds
      act(() => {
        vi.advanceTimersByTime(10000);
      });

      expect(workOrderService.getWorkOrder).toHaveBeenCalledTimes(3);

      vi.useRealTimers();
    });
  });
});
