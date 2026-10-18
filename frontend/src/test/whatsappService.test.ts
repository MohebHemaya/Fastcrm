import { describe, it, expect, vi, beforeEach } from 'vitest';
import { whatsappService } from '@/services/whatsappService';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('whatsappService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('estimateInvites calls POST /drivers/invite-estimate with correct payload', async () => {
    const mockEstimate = { count: 10, send_rate_per_min: 30, estimated_minutes: 1 };
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: mockEstimate, meta: null, message: 'ok' },
    } as any);

    const res = await whatsappService.estimateInvites({ all_new: true });

    expect(api.post).toHaveBeenCalledWith('/drivers/invite-estimate', { all_new: true });
    expect(res.data.count).toBe(10);
  });

  it('inviteDrivers calls POST /drivers/invite with driver IDs', async () => {
    const mockResult = { invited_count: 5, estimated_minutes: 1 };
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: mockResult, meta: null, message: 'تم إرسال الدعوات' },
    } as any);

    const res = await whatsappService.inviteDrivers({ driver_ids: [1, 2, 3] });

    expect(api.post).toHaveBeenCalledWith('/drivers/invite', { driver_ids: [1, 2, 3] });
    expect(res.data.invited_count).toBe(5);
  });

  it('getDriverMessages calls GET /drivers/:id/messages', async () => {
    const mockMessages = [
      { id: 1, body: 'مرحبًا', direction: 'out', status: 'sent', created_at: '2026-09-30T12:00:00Z' },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { data: mockMessages, meta: { driver_id: 1, driver_phone: '+201012345678', can_message: true }, message: null },
    } as any);

    const res = await whatsappService.getDriverMessages(1);

    expect(api.get).toHaveBeenCalledWith('/drivers/1/messages');
    expect(res.data).toHaveLength(1);
    expect(res.meta.can_message).toBe(true);
  });

  it('getMessages calls GET /whatsapp/messages with filter params', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: {
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 0, counts: { total: 0, queued: 0, sent: 0, delivered: 0, read: 0, failed: 0 } },
        message: null,
      },
    } as any);

    await whatsappService.getMessages({ status: 'failed', page: 2 });

    expect(api.get).toHaveBeenCalledWith('/whatsapp/messages', {
      params: { status: 'failed', page: 2 },
    });
  });

  it('retryMessage calls POST /whatsapp/messages/:id/retry', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: { id: 99, status: 'queued' }, meta: null, message: 'retried' },
    } as any);

    const res = await whatsappService.retryMessage(99);

    expect(api.post).toHaveBeenCalledWith('/whatsapp/messages/99/retry');
    expect(res.data.status).toBe('queued');
  });

  it('getProviderStatus calls GET /whatsapp/provider-status', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: {
        data: { provider: 'wassenger', is_fake: false, send_rate_per_minute: 30, invite_batch_size: 50, free_form_window: 24, no_response_days: 3 },
      },
    } as any);

    const res = await whatsappService.getProviderStatus();

    expect(api.get).toHaveBeenCalledWith('/whatsapp/provider-status');
    expect(res.data.provider).toBe('wassenger');
  });


  it('sendDirectMessage calls POST /drivers/:id/send-message', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: { id: 1, body: 'مرحبا' }, message: 'sent' },
    } as any);

    await whatsappService.sendDirectMessage(5, 'مرحبا');

    expect(api.post).toHaveBeenCalledWith('/drivers/5/send-message', {
      message: 'مرحبا',
    });
  });

  it('syncActiveInbound calls POST /whatsapp/sync-active-inbound', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: { synced_drivers: 2, processed_messages: 1 }, message: 'synced' },
    } as any);

    const res = await whatsappService.syncActiveInbound();

    expect(api.post).toHaveBeenCalledWith('/whatsapp/sync-active-inbound', {}, { skipAuthRedirect: true });
    expect(res.data.synced_drivers).toBe(2);
  });

  it('sendMedia calls POST /drivers/:id/send-media with FormData', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { data: { id: 10, type: 'image' }, message: 'sent' },
    } as any);

    const formData = new FormData();
    formData.append('type', 'image');

    const res = await whatsappService.sendMedia(7, formData);

    expect(api.post).toHaveBeenCalledWith('/drivers/7/send-media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    expect(res.data.id).toBe(10);
  });
});
