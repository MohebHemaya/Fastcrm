import { describe, it, expect, vi, beforeEach } from 'vitest';
import { workOrderService } from '@/services/workOrderService';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}));

describe('workOrderService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('getWorkOrders calls GET /work-orders with params', async () => {
    const mockData = {
      data: [{ id: 1, code: 'WO-0001', status: 'draft' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      message: 'success',
    };
    vi.mocked(api.get).mockResolvedValueOnce({ data: mockData } as any);

    const res = await workOrderService.getWorkOrders({ status: 'draft', page: 1 });

    expect(api.get).toHaveBeenCalledWith('/work-orders', {
      params: { status: 'draft', page: 1 },
    });
    expect(res.data).toHaveLength(1);
    expect(res.data[0].code).toBe('WO-0001');
  });

  it('getWorkOrder calls GET /work-orders/:id', async () => {
    const mockOrder = {
      data: { id: 1, code: 'WO-0001', trips_needed: 5 },
      meta: { recipient_counts: { all: 10, confirmed: 5, backup: 2, declined: 1, sent: 2, too_late: 0, failed: 0, cancelled: 0 } },
      message: 'success',
    };
    vi.mocked(api.get).mockResolvedValueOnce({ data: mockOrder } as any);

    const res = await workOrderService.getWorkOrder(1);

    expect(api.get).toHaveBeenCalledWith('/work-orders/1');
    expect(res.data.id).toBe(1);
    expect(res.meta.recipient_counts.confirmed).toBe(5);
  });

  it('createDraft calls POST /work-orders', async () => {
    const payload = {
      from_location: 'Cairo',
      to_location: 'Alexandria',
      scheduled_at: '2026-10-05T10:00:00Z',
      trips_needed: 3,
    };
    const mockRes = { data: { id: 2, code: 'WO-0002', ...payload }, message: 'created' };
    vi.mocked(api.post).mockResolvedValueOnce({ data: mockRes } as any);

    const res = await workOrderService.createDraft(payload as any);

    expect(api.post).toHaveBeenCalledWith('/work-orders', payload);
    expect(res.data.code).toBe('WO-0002');
  });

  it('sendOrder calls POST /work-orders/:id/send', async () => {
    const mockRes = { data: { id: 1, status: 'filling' }, message: 'sent' };
    vi.mocked(api.post).mockResolvedValueOnce({ data: mockRes } as any);

    const res = await workOrderService.sendOrder(1);

    expect(api.post).toHaveBeenCalledWith('/work-orders/1/send');
    expect(res.data.status).toBe('filling');
  });

  it('cancelOrder calls POST /work-orders/:id/cancel with reason', async () => {
    const mockRes = { data: { id: 1, status: 'cancelled' }, message: 'cancelled' };
    vi.mocked(api.post).mockResolvedValueOnce({ data: mockRes } as any);

    const res = await workOrderService.cancelOrder(1, 'العميل ألغى الرحلة');

    expect(api.post).toHaveBeenCalledWith('/work-orders/1/cancel', {
      reason: 'العميل ألغى الرحلة',
    });
    expect(res.data.status).toBe('cancelled');
  });

  it('duplicateOrder calls POST /work-orders/:id/duplicate', async () => {
    const mockRes = { data: { id: 3, code: 'WO-0003', status: 'draft' }, message: 'duplicated' };
    vi.mocked(api.post).mockResolvedValueOnce({ data: mockRes } as any);

    const res = await workOrderService.duplicateOrder(1);

    expect(api.post).toHaveBeenCalledWith('/work-orders/1/duplicate');
    expect(res.data.code).toBe('WO-0003');
  });

  it('previewMatch calls POST /work-orders/match-preview', async () => {
    const payload = {
      filters: [{ question_id: 1, operator: 'eq' as const, value: 'yes' }],
      exclude_same_day_confirmed: true,
    };
    const mockRes = {
      data: { count: 18, drivers: [{ id: 1, name: 'أحمد', phone: '+201012345678', status: 'active' }] },
      message: 'preview',
    };
    vi.mocked(api.post).mockResolvedValueOnce({ data: mockRes } as any);

    const res = await workOrderService.previewMatch(payload);

    expect(api.post).toHaveBeenCalledWith('/work-orders/match-preview', payload, {
      skipAuthRedirect: true,
    });
    expect(res.data.count).toBe(18);
  });
});
