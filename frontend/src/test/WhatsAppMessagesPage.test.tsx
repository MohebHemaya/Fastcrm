import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { HeroUIProvider } from '@heroui/react';
import WhatsAppMessagesPage from '@/routes/WhatsAppMessagesPage';
import { whatsappService } from '@/services/whatsappService';
import { ar } from '@/i18n/ar';

vi.mock('@/services/whatsappService', () => ({
  whatsappService: {
    getMessages: vi.fn(),
    getProviderStatus: vi.fn(),
    retryMessage: vi.fn(),
    getDriverMessages: vi.fn(),
  },
}));

const mockMessages = [
  {
    id: 1,
    driver_id: 101,
    driver_name: 'أحمد محمود',
    driver_phone: '+201012345678',
    direction: 'out',
    direction_label: 'صادرة',
    type: 'template',
    template_name: 'driver_invitation',
    body: 'أهلاً بك يا أحمد في نظام السائقين',
    status: 'failed',
    status_label: 'فشلت',
    error_message: 'Recipient number unreachable',
    created_at: '2026-09-30T10:00:00Z',
  },
  {
    id: 2,
    driver_id: 102,
    driver_name: 'محمد علي',
    driver_phone: '+201098765432',
    direction: 'in',
    direction_label: 'واردة',
    type: 'text',
    template_name: null,
    body: 'نعم، أنا مهتم بالانضمام',
    status: 'delivered',
    status_label: 'وصلت',
    error_message: null,
    created_at: '2026-09-30T11:00:00Z',
  },
];

describe('WhatsAppMessagesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.mocked(whatsappService.getProviderStatus).mockResolvedValue({
      data: {
        provider: 'wassenger',
        is_fake: false,
        send_rate_per_minute: 30,
        invite_batch_size: 50,
        free_form_window: 24,
        no_response_days: 3,
      },
      meta: null,
      message: null,
    });

    vi.mocked(whatsappService.getMessages).mockResolvedValue({
      data: mockMessages as any,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 2,
        counts: { total: 2, queued: 0, sent: 0, delivered: 1, read: 0, failed: 1 },
      },
      message: null,
    });
  });

  it('renders page title, provider indicator, and status tabs with counters', async () => {
    render(
      <HeroUIProvider>
        <WhatsAppMessagesPage />
      </HeroUIProvider>
    );

    expect(screen.getByText(ar.whatsapp.title)).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText(ar.whatsapp.wassengerProvider)).toBeInTheDocument();
    });


    // Switch to logs tab to view message records table
    const logsTab = screen.getByText(/سجل وتفاصيل الرسائل/);
    fireEvent.click(logsTab);

    await waitFor(() => {
      expect(screen.getByText(mockMessages[0].driver_name)).toBeInTheDocument();
      expect(screen.getByText(mockMessages[1].driver_name)).toBeInTheDocument();
    });

    // Check tabs
    expect(screen.getByText(ar.whatsapp.tabs.all)).toBeInTheDocument();
    expect(screen.getAllByText(ar.whatsapp.tabs.failed).length).toBeGreaterThan(0);
  });

  it('triggers retryMessage when clicking retry on a failed message', async () => {
    vi.mocked(whatsappService.retryMessage).mockResolvedValueOnce({
      data: { ...mockMessages[0], status: 'queued' } as any,
      meta: null,
      message: 'retried',
    });

    render(
      <HeroUIProvider>
        <WhatsAppMessagesPage />
      </HeroUIProvider>
    );

    // Switch to logs tab to view message records table
    const logsTab = screen.getByText(/سجل وتفاصيل الرسائل/);
    fireEvent.click(logsTab);

    await waitFor(() => {
      expect(screen.getByText(ar.whatsapp.retry)).toBeInTheDocument();
    });

    const retryButton = screen.getByText(ar.whatsapp.retry);
    fireEvent.click(retryButton);

    expect(whatsappService.retryMessage).toHaveBeenCalledWith(1);
  });
});
