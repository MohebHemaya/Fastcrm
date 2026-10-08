import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { OpenSlotsBanner } from '@/features/workOrders/components/OpenSlotsBanner';
import { PromoteBackupModal } from '@/features/workOrders/components/PromoteBackupModal';
import { RemoveDriverModal } from '@/features/workOrders/components/RemoveDriverModal';
import { RecordOutcomesModal } from '@/features/workOrders/components/RecordOutcomesModal';
import { OrderTimelineTab } from '@/features/workOrders/components/OrderTimelineTab';
import WorkOrderDetailPage from '@/routes/WorkOrderDetailPage';
import DriversPage from '@/routes/DriversPage';
import { workOrderService } from '@/services/workOrderService';
import driverService from '@/services/driverService';
import { ar } from '@/i18n/ar';
import type { WorkOrderRecipient } from '@/types/workOrder';

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
    removeConfirmedDriver: vi.fn(),
    promoteBackup: vi.fn(),
    removeBackupDriver: vi.fn(),
    addDriverManually: vi.fn(),
    resendOffers: vi.fn(),
    changeStatus: vi.fn(),
    recordOutcomes: vi.fn(),
    getEvents: vi.fn(),
  },
}));

vi.mock('@/services/driverService', () => ({
  default: {
    getDrivers: vi.fn(),
    getDriver: vi.fn(),
    getWhatsappGroups: vi.fn(),
    getDriverAnswers: vi.fn(),
    resendQuestion: vi.fn(),
    requestAnswersUpdate: vi.fn(),
    updateDriverAnswer: vi.fn(),
    updateDriver: vi.fn(),
    changeDriverStatus: vi.fn(),
    createDriver: vi.fn(),
  },
  driverService: {
    getDrivers: vi.fn(),
    getDriver: vi.fn(),
    getWhatsappGroups: vi.fn(),
    getDriverAnswers: vi.fn(),
    resendQuestion: vi.fn(),
    requestAnswersUpdate: vi.fn(),
    updateDriverAnswer: vi.fn(),
    updateDriver: vi.fn(),
    changeDriverStatus: vi.fn(),
    createDriver: vi.fn(),
  },
}));

const mockBackups: WorkOrderRecipient[] = [
  {
    id: 201,
    work_order_id: 1,
    driver_id: 11,
    driver_name: 'سائق احتياطي أول',
    driver_phone: '+201011111111',
    status: 'backup',
    status_label: 'احتياطي',
    sequence_number: 1,
    stage_batch: 1,
    sent_at: '2026-10-01T10:00:00Z',
    responded_at: '2026-10-01T10:05:00Z',
  },
  {
    id: 202,
    work_order_id: 1,
    driver_id: 12,
    driver_name: 'سائق احتياطي ثان',
    driver_phone: '+201022222222',
    status: 'backup',
    status_label: 'احتياطي',
    sequence_number: 2,
    stage_batch: 1,
    sent_at: '2026-10-01T10:00:00Z',
    responded_at: '2026-10-01T10:06:00Z',
  },
];

const mockConfirmed: WorkOrderRecipient[] = [
  {
    id: 101,
    work_order_id: 1,
    driver_id: 5,
    driver_name: 'سائق أساسي',
    driver_phone: '+201055555555',
    status: 'confirmed',
    status_label: 'مؤكد',
    sequence_number: 1,
    stage_batch: 1,
    sent_at: '2026-10-01T10:00:00Z',
    responded_at: '2026-10-01T10:02:00Z',
  },
];

describe('Phase 6 Frontend Follow-up Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('OpenSlotsBanner', () => {
    it('renders banner with open slots count and fires action callbacks', async () => {
      const onPromote = vi.fn();
      const onAddDriver = vi.fn();
      const onResendOffers = vi.fn();

      render(
        <HeroUIProvider>
          <OpenSlotsBanner
            openSlotsCount={2}
            onPromote={onPromote}
            onAddDriver={onAddDriver}
            onResendOffers={onResendOffers}
          />
        </HeroUIProvider>
      );

      expect(screen.getByTestId('open-slots-banner')).toBeInTheDocument();
      expect(screen.getByText(/2 مكان شاغر/)).toBeInTheDocument();

      fireEvent.click(screen.getByText(ar.workOrders.openSlotsBanner.promoteButton));
      expect(onPromote).toHaveBeenCalledTimes(1);

      fireEvent.click(screen.getByText(ar.workOrders.openSlotsBanner.addDriverButton));
      expect(onAddDriver).toHaveBeenCalledTimes(1);

      fireEvent.click(screen.getByText(ar.workOrders.openSlotsBanner.resendButton));
      expect(onResendOffers).toHaveBeenCalledTimes(1);
    });

    it('renders nothing when openSlotsCount is 0', () => {
      render(
        <HeroUIProvider>
          <OpenSlotsBanner
            openSlotsCount={0}
            onPromote={vi.fn()}
            onAddDriver={vi.fn()}
            onResendOffers={vi.fn()}
          />
        </HeroUIProvider>
      );
      expect(screen.queryByTestId('open-slots-banner')).toBeNull();
    });
  });

  describe('PromoteBackupModal', () => {
    it('pre-selects lowest sequence backup and calls onConfirm with recipientId', async () => {
      const onConfirm = vi.fn().mockResolvedValue(undefined);
      const onClose = vi.fn();

      render(
        <HeroUIProvider>
          <PromoteBackupModal
            isOpen={true}
            onClose={onClose}
            backups={mockBackups}
            onConfirm={onConfirm}
          />
        </HeroUIProvider>
      );

      expect(screen.getByText(ar.workOrders.promoteModal.title)).toBeInTheDocument();
      expect(screen.getByText('سائق احتياطي أول')).toBeInTheDocument();
      expect(screen.getByText('سائق احتياطي ثان')).toBeInTheDocument();

      const confirmBtn = screen.getByText(ar.workOrders.promoteModal.confirm);
      fireEvent.click(confirmBtn);

      await waitFor(() => {
        expect(onConfirm).toHaveBeenCalledWith(201);
      });
    });
  });

  describe('RemoveDriverModal', () => {
    it('validates required reason and confirms removal', async () => {
      const onConfirm = vi.fn().mockResolvedValue(undefined);
      const onClose = vi.fn();

      render(
        <HeroUIProvider>
          <RemoveDriverModal
            isOpen={true}
            onClose={onClose}
            recipient={mockConfirmed[0]}
            isConfirmed={true}
            onConfirm={onConfirm}
          />
        </HeroUIProvider>
      );

      expect(screen.getByText(ar.workOrders.removeDriverModal.titleConfirmed)).toBeInTheDocument();
      expect(screen.getByText('سائق أساسي')).toBeInTheDocument();

      // Submit without reason
      const submitBtn = screen.getByText(ar.workOrders.removeDriverModal.confirm);
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(screen.getAllByText(ar.workOrders.removeDriverModal.reasonRequired)[0]).toBeInTheDocument();
        expect(onConfirm).not.toHaveBeenCalled();
      });

      // Enter reason and submit
      const input = screen.getByPlaceholderText(ar.workOrders.removeDriverModal.reasonPlaceholder);
      fireEvent.change(input, { target: { value: 'عطل مفاجئ بالسيارة' } });

      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(onConfirm).toHaveBeenCalledWith('عطل مفاجئ بالسيارة');
      });
    });
  });

  describe('RecordOutcomesModal', () => {
    it('allows recording outcome and rating for confirmed drivers', async () => {
      const onConfirm = vi.fn().mockResolvedValue(undefined);
      const onClose = vi.fn();

      render(
        <HeroUIProvider>
          <RecordOutcomesModal
            isOpen={true}
            onClose={onClose}
            confirmedRecipients={mockConfirmed}
            onConfirm={onConfirm}
          />
        </HeroUIProvider>
      );

      expect(screen.getByText(ar.workOrders.outcomes.title)).toBeInTheDocument();
      expect(screen.getByText('سائق أساسي')).toBeInTheDocument();

      const saveBtn = screen.getByText(ar.workOrders.outcomes.save);
      fireEvent.click(saveBtn);

      await waitFor(() => {
        expect(onConfirm).toHaveBeenCalledWith([
          {
            recipient_id: 101,
            outcome: 'attended',
            rating: null,
            notes: null,
          },
        ]);
      });
    });
  });

  describe('OrderTimelineTab', () => {
    it('fetches events and renders timeline cards', async () => {
      vi.mocked(workOrderService.getEvents).mockResolvedValueOnce({
        data: [
          {
            id: 1,
            work_order_id: 1,
            user_id: 1,
            user_name: 'مدير العمليات',
            driver_id: 11,
            driver_name: 'سائق احتياطي أول',
            action: 'backup_promoted',
            description: 'تمت ترقية السائق الاحتياطي إلى مقعد مؤكد',
            metadata: { recipient_id: 201 },
            created_at: '2026-10-01T12:00:00Z',
          },
        ],
        meta: null,
        message: 'ok',
      });

      render(
        <HeroUIProvider>
          <OrderTimelineTab orderId={1} />
        </HeroUIProvider>
      );

      await waitFor(() => {
        expect(workOrderService.getEvents).toHaveBeenCalledWith(1);
        expect(screen.getByText('تمت ترقية السائق الاحتياطي إلى مقعد مؤكد')).toBeInTheDocument();
        expect(screen.getByText('مدير العمليات')).toBeInTheDocument();
        expect(screen.getByText('سائق احتياطي أول')).toBeInTheDocument();
      });
    });
  });

  describe('WorkOrderDetailPage Open Slots & Timeline Integration', () => {
    it('displays open slots banner and timeline tab when order has open slots', async () => {
      vi.mocked(workOrderService.getWorkOrder).mockResolvedValueOnce({
        data: {
          id: 1,
          code: 'WO-0001',
          from_location: 'العاشر من رمضان',
          to_location: 'ميناء الإسكندرية',
          scheduled_at: '2026-10-05T08:00:00Z',
          cargo_type: 'بضائع عامة',
          pay_amount: 1500,
          notes: null,
          trips_needed: 10,
          backup_needed: 5,
          status: 'filled',
          status_label: 'مكتمل العدد',
          confirmed_count: 9,
          backup_count: 5,
          sent_count: 20,
          filled_at: '2026-10-01T10:00:00Z',
          send_strategy: 'all',
          batch_size: null,
          batch_interval_minutes: null,
          exclude_same_day_confirmed: true,
          has_open_slots: true,
          open_slots_count: 1,
          created_at: '2026-10-01T10:00:00Z',
        },
        meta: {
          recipient_counts: {
            all: 20,
            confirmed: 9,
            backup: 5,
            declined: 1,
            sent: 5,
            too_late: 0,
            failed: 0,
            cancelled: 0,
          },
        },
        message: 'ok',
      });

      vi.mocked(workOrderService.getRecipients).mockResolvedValue({
        data: mockConfirmed,
        meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 },
        message: 'ok',
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

      // Verify open slots banner appears
      await waitFor(() => {
        expect(screen.getByTestId('open-slots-banner')).toBeInTheDocument();
        expect(screen.getByText(/1 مكان شاغر/)).toBeInTheDocument();
      });

      // Verify timeline tab exists
      expect(screen.getByText(ar.workOrders.timeline.tabTitle)).toBeInTheDocument();
    });
  });

  describe('DriversPage Metrics Integration', () => {
    it('renders metrics columns (trips_completed, avg_rating, no_shows)', async () => {
      vi.mocked(driverService.getDrivers).mockResolvedValueOnce({
        data: [
          {
            id: 1,
            phone: '+201012345678',
            name: 'كابتن محمود',
            status: { value: 'active', label: 'نشط' },
            opted_out_at: null,
            notes: null,
            groups: [],
            created_at: '2026-09-01T00:00:00Z',
            updated_at: '2026-09-01T00:00:00Z',
            metrics: {
              trips_completed: 15,
              cancellations: 1,
              no_shows: 0,
              late_count: 2,
              avg_rating: 4.8,
              ratings_count: 12,
              last_trip_at: '2026-10-01T00:00:00Z',
            },
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
        message: null,
      });

      vi.mocked(driverService.getWhatsappGroups).mockResolvedValueOnce({
        data: [],
        meta: null as any,
        message: null,
      });

      render(
        <HeroUIProvider>
          <MemoryRouter>
            <DriversPage />
          </MemoryRouter>
        </HeroUIProvider>
      );

      await waitFor(() => {
        expect(screen.getByText('كابتن محمود')).toBeInTheDocument();
        expect(screen.getByText(ar.driverMetrics.tripsCompleted)).toBeInTheDocument();
        expect(screen.getByText(ar.driverMetrics.avgRating)).toBeInTheDocument();
        expect(screen.getByText(ar.driverMetrics.noShows)).toBeInTheDocument();
        expect(screen.getByText('15')).toBeInTheDocument();
        expect(screen.getByText(/4.8/)).toBeInTheDocument();
      });
    });
  });
});
