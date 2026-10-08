import { api, type ApiEnvelope } from '@/lib/api';
import type {
  WhatsAppMessage,
  WhatsAppMessagesMeta,
  WhatsAppProviderStatus,
  InviteEstimate,
  DriverChatMeta,
} from '@/types/whatsapp';

export interface WhatsAppMessageFilterParams {
  page?: number;
  status?: string;
  direction?: string;
  driver_id?: number;
  search?: string;
}

export interface WhatsAppMessagesResponse {
  data: WhatsAppMessage[];
  meta: WhatsAppMessagesMeta;
  message: string | null;
}

export interface DriverMessagesResponse {
  data: WhatsAppMessage[];
  meta: DriverChatMeta;
  message: string | null;
}

export interface InvitePayload {
  driver_ids?: number[];
  all_new?: boolean;
}

export interface InviteResult {
  invited_count: number;
  estimated_minutes: number;
}

export const whatsappService = {
  /**
   * Preview count and duration for driver invitations
   */
  async estimateInvites(payload: InvitePayload): Promise<ApiEnvelope<InviteEstimate>> {
    const response = await api.post<ApiEnvelope<InviteEstimate>>('/drivers/invite-estimate', payload);
    return response.data;
  },

  /**
   * Bulk send invitations to drivers with status NEW
   */
  async inviteDrivers(payload: InvitePayload): Promise<ApiEnvelope<InviteResult>> {
    const response = await api.post<ApiEnvelope<InviteResult>>('/drivers/invite', payload);
    return response.data;
  },

  /**
   * Get chat log for a specific driver
   */
  async getDriverMessages(driverId: number): Promise<DriverMessagesResponse> {
    const response = await api.get<DriverMessagesResponse>(`/drivers/${driverId}/messages`);
    return response.data;
  },

  /**
   * Get global WhatsApp message log with pagination and filters
   */
  async getMessages(params?: WhatsAppMessageFilterParams): Promise<WhatsAppMessagesResponse> {
    const response = await api.get<WhatsAppMessagesResponse>('/whatsapp/messages', { params });
    return response.data;
  },

  /**
   * Retry a failed outgoing WhatsApp message
   */
  async retryMessage(messageId: number): Promise<ApiEnvelope<WhatsAppMessage>> {
    const response = await api.post<ApiEnvelope<WhatsAppMessage>>(`/whatsapp/messages/${messageId}/retry`);
    return response.data;
  },

  /**
   * Get current WhatsApp provider status (Fake vs Cloud)
   */
  async getProviderStatus(): Promise<ApiEnvelope<WhatsAppProviderStatus>> {
    const response = await api.get<ApiEnvelope<WhatsAppProviderStatus>>('/whatsapp/provider-status');
    return response.data;
  },

  /**
   * Simulate an incoming WhatsApp message (Local/Test mode)
   */
  async simulateInbound(phone: string, text?: string, payloadId?: string): Promise<ApiEnvelope<any>> {
    const response = await api.post<ApiEnvelope<any>>('/whatsapp/simulate-inbound', {
      phone,
      text,
      payload_id: payloadId,
    });
    return response.data;
  },

  /**
   * Simulate a status update (Local/Test mode)
   */
  async simulateStatus(messageId: number | string, status: string, errorCode?: string): Promise<ApiEnvelope<WhatsAppMessage>> {
    const response = await api.post<ApiEnvelope<WhatsAppMessage>>('/whatsapp/simulate-status', {
      message_id: messageId,
      status,
      error_code: errorCode,
    });
    return response.data;
  },

  /**
   * Get recent conversations list for the split-pane WhatsApp inbox
   */
  async getConversations(params?: { search?: string; page?: number }): Promise<{
    data: import('@/types/whatsapp').ConversationItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    message: string | null;
  }> {
    const response = await api.get('/whatsapp/conversations', { params });
    return response.data;
  },

  /**
   * Send direct text message from operator to driver
   */
  async sendDirectMessage(driverId: number, message: string): Promise<ApiEnvelope<WhatsAppMessage>> {
    const response = await api.post<ApiEnvelope<WhatsAppMessage>>(`/drivers/${driverId}/send-message`, {
      message,
    });
    return response.data;
  },

  /**
   * Get customizable message templates and quick replies
   */
  async getTemplates(): Promise<ApiEnvelope<import('@/types/whatsapp').WhatsAppTemplatesData>> {
    const response = await api.get<ApiEnvelope<import('@/types/whatsapp').WhatsAppTemplatesData>>('/whatsapp/templates');
    return response.data;
  },

  /**
   * Update message templates and quick replies
   */
  async updateTemplates(payload: {
    templates?: Record<string, string>;
    quick_replies?: import('@/types/whatsapp').QuickReplyItem[];
  }): Promise<ApiEnvelope<import('@/types/whatsapp').WhatsAppTemplatesData>> {
    const response = await api.put<ApiEnvelope<import('@/types/whatsapp').WhatsAppTemplatesData>>('/whatsapp/templates', payload);
    return response.data;
  },
};
