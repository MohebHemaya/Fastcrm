export type DriverStatusValue =
  | 'new'
  | 'invited'
  | 'interested'
  | 'questionnaire_in_progress'
  | 'contacted'
  | 'qualified'
  | 'training_scheduled'
  | 'trained'
  | 'active'
  | 'declined'
  | 'no_response'
  | 'suspended'
  | 'blocked'
  | 'opted_out';

export interface DriverStatusInfo {
  value: DriverStatusValue;
  label: string;
}

export interface WhatsappGroup {
  id: number;
  name: string;
  drivers_count?: number;
  first_seen_at?: string | null;
  created_at?: string;
}

export interface DriverStatusHistory {
  id: number;
  from_status: DriverStatusValue | null;
  from_status_label: string | null;
  to_status: DriverStatusValue;
  to_status_label: string;
  reason: string | null;
  changed_by: {
    id: number;
    name: string;
  } | null;
  created_at: string;
}

export interface DriverMetrics {
  trips_completed: number;
  cancellations: number;
  no_shows: number;
  late_count: number;
  avg_rating: number | null;
  ratings_count: number;
  last_trip_at: string | null;
}

export interface Driver {
  id: number;
  phone: string;
  name: string | null;
  status: DriverStatusInfo;
  opted_out_at: string | null;
  notes: string | null;
  groups: WhatsappGroup[];
  status_histories?: DriverStatusHistory[];
  metrics?: DriverMetrics | null;
  answers_count?: number;
  is_new_to_fleet?: boolean;
  created_at: string;
  updated_at: string;
}

export interface DriverFilterParams {
  search?: string;
  status?: string;
  pool?: 'new' | 'fleet';
  group_id?: number | string;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface CreateDriverPayload {
  phone: string;
  name?: string;
  group_id?: number | string;
  group_name?: string;
  notes?: string;
}

export interface UpdateDriverPayload {
  name?: string;
  notes?: string;
}

export interface ChangeDriverStatusPayload {
  status: DriverStatusValue;
  reason?: string;
}

/**
 * State machine allowed transitions mapping (Frontend reference & validation)
 */
export const ALLOWED_STATUS_TRANSITIONS: Record<DriverStatusValue, DriverStatusValue[]> = {
  new: ['invited', 'questionnaire_in_progress', 'active', 'contacted', 'no_response', 'blocked', 'opted_out'],
  invited: ['interested', 'questionnaire_in_progress', 'active', 'declined', 'no_response', 'blocked', 'opted_out'],
  interested: ['questionnaire_in_progress', 'active', 'declined', 'no_response', 'blocked', 'opted_out'],
  questionnaire_in_progress: ['active', 'declined', 'no_response', 'blocked', 'opted_out'],
  contacted: ['qualified', 'declined', 'no_response', 'blocked', 'opted_out'],
  qualified: ['training_scheduled', 'declined', 'no_response', 'blocked', 'opted_out'],
  training_scheduled: ['trained', 'declined', 'no_response', 'blocked', 'opted_out'],
  trained: ['active', 'declined', 'suspended', 'blocked', 'opted_out'],
  active: ['suspended', 'blocked', 'opted_out'],
  declined: ['contacted', 'new', 'blocked', 'opted_out'],
  no_response: ['contacted', 'new', 'blocked', 'opted_out'],
  suspended: ['active', 'blocked', 'opted_out'],
  blocked: ['suspended', 'contacted', 'active'], // manual admin override
  opted_out: [], // terminal
};
