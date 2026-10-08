import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { HeroUIProvider } from '@heroui/react';
import BulkInviteModal from '@/features/whatsapp/components/BulkInviteModal';
import DriverChatModal from '@/features/whatsapp/components/DriverChatModal';
import { whatsappService } from '@/services/whatsappService';
import { ar } from '@/i18n/ar';

vi.mock('@/services/whatsappService', () => ({
  whatsappService: {
    estimateInvites: vi.fn(),
    inviteDrivers: vi.fn(),
    getDriverMessages: vi.fn(),
    simulateInbound: vi.fn(),
    retryMessage: vi.fn(),
  },
}));

describe('WhatsApp Modals', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('BulkInviteModal', () => {
    it('fetches estimate and submits invitations on confirm', async () => {
      vi.mocked(whatsappService.estimateInvites).mockResolvedValueOnce({
        data: { count: 8, send_rate_per_min: 30, estimated_minutes: 1 },
        meta: null,
        message: null,
      });

      vi.mocked(whatsappService.inviteDrivers).mockResolvedValueOnce({
        data: { invited_count: 8, estimated_minutes: 1 },
        meta: null,
        message: 'تمت الجدولة',
      });

      const handleSuccess = vi.fn();
      const handleClose = vi.fn();

      render(
        <HeroUIProvider>
          <BulkInviteModal
            isOpen={true}
            onClose={handleClose}
            onSuccess={handleSuccess}
            selectedDriverIds={[1, 2, 3]}
          />
        </HeroUIProvider>
      );

      expect(screen.getByText(ar.whatsapp.inviteConfirmTitle)).toBeInTheDocument();

      await waitFor(() => {
        expect(screen.getByText('8')).toBeInTheDocument();
      });

      const confirmButton = screen.getByText(ar.whatsapp.confirmSend);
      fireEvent.click(confirmButton);

      await waitFor(() => {
        expect(whatsappService.inviteDrivers).toHaveBeenCalled();
        expect(handleSuccess).toHaveBeenCalled();
      });
    });
  });

  describe('DriverChatModal', () => {
    it('loads chat messages and sends simulation event', async () => {
      const mockChatMessages = [
        {
          id: 10,
          driver_id: 5,
          direction: 'out',
          direction_label: 'صادرة',
          type: 'template',
          template_name: 'driver_invitation',
          body: 'دعوة انضمام',
          status: 'sent',
          status_label: 'أُرسلت',
          created_at: '2026-09-30T10:00:00Z',
        },
      ];

      vi.mocked(whatsappService.getDriverMessages).mockResolvedValueOnce({
        data: mockChatMessages as any,
        meta: {
          driver_id: 5,
          driver_name: 'كابتن طارق',
          driver_phone: '+201011223344',
          driver_status: 'invited',
          can_message: true,
        },
        message: null,
      });

      vi.mocked(whatsappService.simulateInbound).mockResolvedValueOnce({
        data: { handled: true },
        meta: null,
        message: 'simulated',
      });

      render(
        <HeroUIProvider>
          <DriverChatModal
            isOpen={true}
            driverId={5}
            driverName="كابتن طارق"
            driverPhone="+201011223344"
            onClose={vi.fn()}
          />
        </HeroUIProvider>
      );

      await waitFor(() => {
        expect(screen.getByText('دعوة انضمام')).toBeInTheDocument();
      });

      // Click "نعم، مهتم" simulation button
      const simButton = screen.getByText(ar.whatsapp.simulateYes);
      fireEvent.click(simButton);

      await waitFor(() => {
        expect(whatsappService.simulateInbound).toHaveBeenCalledWith(
          '+201011223344',
          'نعم، مهتم',
          'invite_yes'
        );
      });
    });
  });
});
