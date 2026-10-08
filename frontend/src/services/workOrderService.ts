import { api } from '@/lib/api';
import type {
  WorkOrder,
  WorkOrderQueryParams,
  CreateWorkOrderPayload,
  UpdateWorkOrderPayload,
  WorkOrderRecipient,
  MatchPreviewPayload,
  MatchPreviewResponse,
  WorkOrderShowMeta,
} from '@/types/workOrder';

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message?: string | null;
}

export interface SingleResponse<T, M = unknown> {
  data: T;
  meta: M;
  message?: string | null;
}

export const workOrderService = {
  /**
   * Get paginated work orders with optional filters.
   */
  async getWorkOrders(params?: WorkOrderQueryParams): Promise<PaginatedResponse<WorkOrder>> {
    const res = await api.get<PaginatedResponse<WorkOrder>>('/work-orders', { params });
    return res.data;
  },

  /**
   * Get single work order details with recipient counts.
   */
  async getWorkOrder(id: number): Promise<SingleResponse<WorkOrder, WorkOrderShowMeta>> {
    const res = await api.get<SingleResponse<WorkOrder, WorkOrderShowMeta>>(`/work-orders/${id}`);
    return res.data;
  },

  /**
   * Create a new work order draft.
   */
  async createDraft(payload: CreateWorkOrderPayload): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>('/work-orders', payload);
    return res.data;
  },

  /**
   * Update an existing work order draft.
   */
  async updateDraft(id: number, payload: UpdateWorkOrderPayload): Promise<SingleResponse<WorkOrder>> {
    const res = await api.put<SingleResponse<WorkOrder>>(`/work-orders/${id}`, payload);
    return res.data;
  },

  /**
   * Start sending offers to matching drivers.
   */
  async sendOrder(id: number): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${id}/send`);
    return res.data;
  },

  /**
   * Cancel an active or filling work order.
   */
  async cancelOrder(id: number, reason?: string): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${id}/cancel`, { reason });
    return res.data;
  },

  /**
   * Duplicate a work order into a new draft.
   */
  async duplicateOrder(id: number): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${id}/duplicate`);
    return res.data;
  },

  /**
   * Get recipients for a work order, optionally filtered by status.
   */
  async getRecipients(
    id: number,
    params?: { status?: string; page?: number; per_page?: number }
  ): Promise<PaginatedResponse<WorkOrderRecipient>> {
    const res = await api.get<PaginatedResponse<WorkOrderRecipient>>(`/work-orders/${id}/recipients`, {
      params,
    });
    return res.data;
  },

  /**
   * Retry sending offer to a failed recipient.
   */
  async retryRecipient(orderId: number, recipientId: number): Promise<SingleResponse<WorkOrderRecipient>> {
    const res = await api.post<SingleResponse<WorkOrderRecipient>>(
      `/work-orders/${orderId}/recipients/${recipientId}/retry`
    );
    return res.data;
  },

  /**
   * Preview matching drivers count and sample list for given filters.
   */
  async previewMatch(payload: MatchPreviewPayload): Promise<SingleResponse<MatchPreviewResponse>> {
    const res = await api.post<SingleResponse<MatchPreviewResponse>>('/work-orders/match-preview', payload);
    return res.data;
  },

  /**
   * Remove a confirmed driver with reason.
   */
  async removeConfirmedDriver(
    orderId: number,
    recipientId: number,
    reason: string
  ): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${orderId}/remove-confirmed`, {
      recipient_id: recipientId,
      reason,
    });
    return res.data;
  },

  /**
   * Promote a backup driver to confirmed.
   */
  async promoteBackup(
    orderId: number,
    recipientId?: number
  ): Promise<SingleResponse<WorkOrderRecipient>> {
    const res = await api.post<SingleResponse<WorkOrderRecipient>>(`/work-orders/${orderId}/promote-backup`, {
      recipient_id: recipientId,
    });
    return res.data;
  },

  /**
   * Remove a backup driver with reason.
   */
  async removeBackupDriver(
    orderId: number,
    recipientId: number,
    reason: string
  ): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${orderId}/remove-backup`, {
      recipient_id: recipientId,
      reason,
    });
    return res.data;
  },

  /**
   * Add a driver manually as confirmed or backup.
   */
  async addDriverManually(
    orderId: number,
    driverId: number,
    as: 'confirmed' | 'backup'
  ): Promise<SingleResponse<WorkOrderRecipient>> {
    const res = await api.post<SingleResponse<WorkOrderRecipient>>(`/work-orders/${orderId}/add-driver`, {
      driver_id: driverId,
      as,
    });
    return res.data;
  },

  /**
   * Resend offers to no_response / failed recipients and reopen order to filling.
   */
  async resendOffers(
    orderId: number,
    recipientIds?: number[]
  ): Promise<SingleResponse<{ resent_count: number; status: string }>> {
    const res = await api.post<SingleResponse<{ resent_count: number; status: string }>>(
      `/work-orders/${orderId}/resend-offers`,
      { recipient_ids: recipientIds }
    );
    return res.data;
  },

  /**
   * Advance order status (in_progress, completed, cancelled).
   */
  async changeStatus(
    orderId: number,
    status: 'in_progress' | 'completed' | 'cancelled',
    reason?: string
  ): Promise<SingleResponse<WorkOrder>> {
    const res = await api.post<SingleResponse<WorkOrder>>(`/work-orders/${orderId}/status`, {
      status,
      reason,
    });
    return res.data;
  },

  /**
   * Record outcomes and ratings for confirmed drivers.
   */
  async recordOutcomes(
    orderId: number,
    outcomes: Array<{ recipient_id: number; outcome: string; rating?: number | null; notes?: string | null }>
  ): Promise<SingleResponse<null>> {
    const res = await api.post<SingleResponse<null>>(`/work-orders/${orderId}/outcomes`, { outcomes });
    return res.data;
  },

  /**
   * Get timeline events for an order.
   */
  async getEvents(orderId: number): Promise<SingleResponse<import('@/types/workOrder').WorkOrderEvent[]>> {
    const res = await api.get<SingleResponse<import('@/types/workOrder').WorkOrderEvent[]>>(
      `/work-orders/${orderId}/events`
    );
    return res.data;
  },
};

export default workOrderService;
