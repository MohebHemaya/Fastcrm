export type WorkOrderStatus =
  | 'draft'
  | 'sending'
  | 'filling'
  | 'filled'
  | 'in_progress'
  | 'completed'
  | 'cancelled';

export type WorkOrderRecipientStatus =
  | 'pending'
  | 'sent'
  | 'confirmed'
  | 'backup'
  | 'declined'
  | 'too_late'
  | 'no_response'
  | 'failed'
  | 'cancelled';

export type WorkOrderFilterOperator = 'in' | 'eq' | 'gte' | 'lte';

export interface WorkOrderFilter {
  id?: number;
  question_id: number;
  question_key?: string;
  question_text?: string;
  operator: WorkOrderFilterOperator;
  value: any;
}

export interface WorkOrder {
  id: number;
  code: string;
  from_location?: string | null;
  to_location?: string | null;
  scheduled_at?: string | null;
  scheduled_dispatch_at?: string | null;
  offer_expires_in_minutes?: number | null;
  cargo_type: string | null;
  pay_amount: number | string | null;
  notes: string | null;
  custom_message?: string | null;
  trips_needed: number;
  backup_needed: number;
  status: WorkOrderStatus;
  status_label?: string;
  confirmed_count: number;
  backup_count: number;
  sent_count: number;
  filled_at: string | null;
  send_strategy: 'all' | 'staged';
  batch_size: number | null;
  batch_interval_minutes: number | null;
  exclude_same_day_confirmed: boolean;
  has_open_slots?: boolean;
  open_slots_count?: number;
  min_avg_rating?: number | null;
  max_no_shows?: number | null;
  last_trip_within_days?: number | null;
  include_unrated?: boolean;
  cancellation_reason?: string | null;
  created_at: string;
  created_by?: number | null;
  creator_name?: string | null;
  filters?: WorkOrderFilter[];
}

export interface WorkOrderRecipient {
  id: number;
  work_order_id: number;
  driver_id: number;
  driver_name: string;
  driver_phone: string;
  status: WorkOrderRecipientStatus;
  status_label?: string;
  sequence_number: number | null;
  stage_batch: number | null;
  sent_at: string | null;
  responded_at: string | null;
  outcome?: 'attended' | 'late' | 'no_show' | 'cancelled_late' | null;
  outcome_label?: string | null;
  rating?: number | null;
  outcome_notes?: string | null;
  outcome_recorded_at?: string | null;
  cancellation_reason?: string | null;
  cancelled_at?: string | null;
  reminder_sent_at?: string | null;
}

export interface WorkOrderRecipientCounts {
  all: number;
  confirmed: number;
  backup: number;
  declined: number;
  sent: number;
  too_late: number;
  failed: number;
  cancelled: number;
}

export interface WorkOrderEvent {
  id: number;
  work_order_id: number;
  user_id?: number | null;
  user_name?: string | null;
  driver_id?: number | null;
  driver_name?: string | null;
  action: string;
  description: string;
  metadata?: Record<string, any> | null;
  created_at: string;
}

export interface RecordOutcomeItem {
  recipient_id: number;
  outcome: 'attended' | 'late' | 'no_show' | 'cancelled_late';
  rating?: number | null;
  notes?: string | null;
}

export interface WorkOrderQueryParams {
  status?: string;
  search?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
}

export interface CreateWorkOrderPayload {
  from_location?: string;
  to_location?: string;
  scheduled_at?: string;
  scheduled_dispatch_at?: string;
  offer_expires_in_minutes?: number;
  cargo_type?: string;
  pay_amount?: number;
  notes?: string;
  custom_message?: string;
  trips_needed: number;
  backup_needed?: number;
  send_strategy?: 'all' | 'staged';
  batch_size?: number;
  batch_interval_minutes?: number;
  exclude_same_day_confirmed?: boolean;
  min_avg_rating?: number | null;
  max_no_shows?: number | null;
  last_trip_within_days?: number | null;
  include_unrated?: boolean;
  target_group_id?: number | null;
  driver_experience?: 'all' | 'new_only' | 'experienced_only';
  filters?: Array<{
    question_id: number;
    operator: WorkOrderFilterOperator;
    value: any;
  }>;
}

export type UpdateWorkOrderPayload = Partial<CreateWorkOrderPayload>;

export interface MatchPreviewPayload {
  filters?: Array<{
    question_id: number;
    operator: WorkOrderFilterOperator;
    value: any;
  }>;
  exclude_same_day_confirmed?: boolean;
  scheduled_at?: string;
  min_avg_rating?: number | null;
  max_no_shows?: number | null;
  last_trip_within_days?: number | null;
  include_unrated?: boolean;
  target_group_id?: number | null;
  driver_experience?: 'all' | 'new_only' | 'experienced_only';
  phone?: string;
}

export interface MatchPreviewResponse {
  count: number;
  drivers: Array<{
    id: number;
    name: string | null;
    phone: string;
    status: string;
  }>;
}

export interface WorkOrderShowMeta {
  recipient_counts: WorkOrderRecipientCounts;
}
