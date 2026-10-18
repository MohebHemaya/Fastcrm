export type WhatsAppDirection = 'out' | 'in';
export type WhatsAppStatus = 'queued' | 'sent' | 'delivered' | 'read' | 'failed';
export type WhatsAppMessageStatus = WhatsAppStatus;
export type WhatsAppMessageType = 'template' | 'text' | 'button' | 'list' | 'image' | 'audio' | 'document' | 'other';

export interface WhatsAppMessage {
  id: number;
  driver_id: number;
  driver_name?: string | null;
  driver_phone?: string | null;
  direction: WhatsAppDirection;
  direction_label: string;
  type: WhatsAppMessageType;
  template_name?: string | null;
  body?: string | null;
  payload?: any;
  status: WhatsAppStatus;
  status_label: string;
  error_code?: string | null;
  error_message?: string | null;
  provider_message_id?: string | null;
  order_id?: number | null;
  queued_at?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
}

export interface WhatsAppStatusCounts {
  total: number;
  queued: number;
  sent: number;
  delivered: number;
  read: number;
  failed: number;
}

export interface WhatsAppMessagesMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  counts: WhatsAppStatusCounts;
}

export interface WhatsAppProviderStatus {
  provider: 'wassenger' | 'cloud' | string;
  is_fake?: boolean;

  send_rate_per_minute: number;
  invite_batch_size: number;
  free_form_window: number;
  no_response_days: number;
  anti_ban?: {
    enabled: boolean;
    min_delay_seconds: number;
    max_delay_seconds: number;
    batch_size: number;
    batch_pause_seconds: number;
  };
}

export interface InviteEstimate {
  count: number;
  send_rate_per_min: number;
  estimated_minutes: number;
  anti_ban_enabled?: boolean;
  anti_ban_min_delay?: number;
  anti_ban_max_delay?: number;
}

export interface MessageStatusUpdatedPayload {
  driver_id: number;
  message_id: number;
  status: WhatsAppStatus;
  delivered_at?: string | null;
  read_at?: string | null;
  error_message?: string | null;
}

export interface DriverChatMeta {
  driver_id: number;
  driver_name?: string | null;
  driver_phone: string;
  driver_status: string;
  can_message: boolean;
}

export interface ConversationItem {
  id: number;
  name: string;
  phone: string;
  status: string;
  last_message: {
    id: number;
    body?: string | null;
    direction: WhatsAppDirection;
    status: WhatsAppStatus;
    created_at: string;
  } | null;
  unread_count: number;
}

export interface WhatsAppTemplateVariable {
  tag: string;
  label_ar: string;
}

export interface WhatsAppTemplateDefinition {
  key: string;
  title_ar: string;
  description_ar: string;
  default_text: string;
  current_text: string;
  variables: WhatsAppTemplateVariable[];
}

export interface QuickReplyItem {
  id: string;
  title: string;
  text: string;
}

export interface WhatsAppTemplatesData {
  templates: WhatsAppTemplateDefinition[];
  quick_replies: QuickReplyItem[];
}
