import { api, type ApiEnvelope } from '@/lib/api';

export interface ActivityLogUser {
  id: number;
  name: string;
  email: string;
}

export interface ActivityLogItem {
  id: number;
  user: ActivityLogUser | null;
  action: string;
  subject_type: string | null;
  subject_id: number | null;
  properties: Record<string, any> | null;
  ip: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface ActivityLogsFilterParams {
  user_id?: string | number;
  action?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
}

export interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from?: number;
  to?: number;
}

export interface PaginatedResponse<T> extends ApiEnvelope<T[]> {
  meta: PaginatedMeta;
}

export const activityLogService = {
  async getActivityLogs(params?: ActivityLogsFilterParams): Promise<PaginatedResponse<ActivityLogItem>> {
    const response = await api.get<PaginatedResponse<ActivityLogItem>>('/activity-logs', {
      params,
    });
    return response.data;
  },
};

export default activityLogService;
