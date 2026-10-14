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
    getNewMessages: vi.fn().mockResolvedValue({ data: [], meta: { statuses: [] } }),
    sendDirectMessage: vi.fn(),
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
    it('loads chat messages and sends direct message to driver', async () => {
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

      vi.mocked(whatsappService.sendDirectMessage).mockResolvedValueOnce({
        data: { id: 11, status: 'queued' } as any,
        meta: null,
        message: 'sent',
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

      // Type in direct WhatsApp message input
      const input = screen.getByPlaceholderText('اكتب ردك للسائق هنا عبر واتساب...');
      fireEvent.change(input, { target: { value: 'مرحباً كابتن طارق' } });

      const sendButton = screen.getByRole('button', { name: 'إرسال' });
      fireEvent.click(sendButton);

      await waitFor(() => {
        expect(whatsappService.sendDirectMessage).toHaveBeenCalledWith(5, 'مرحباً كابتن طارق');
      });
    });
  });
});
