import { api } from '@/lib/api';

export interface SchedulerHealth {
  status: 'ok' | 'warning';
  last_heartbeat_at: string | null;
  age_seconds: number | null;
  age_minutes: number | null;
  is_stale: boolean;
  warning_threshold_minutes: number;
}

export interface QueueHealth {
  backlog_size: number;
  oldest_job_age_seconds: number | null;
  status: 'ok' | 'warning';
}

export interface FailedJobItem {
  id: number;
  uuid: string;
  name: string;
  queue: string;
  failed_at: string;
  exception_preview: string;
}

export interface FailedJobsHealth {
  count: number;
  recent: FailedJobItem[];
}

export interface WebhookHealth {
  last_received_at: string | null;
  age_minutes: number | null;
}

export interface WhatsAppHealth {
  provider: string;
  graph_version: string;
  failed_messages_24h: number;
}

export interface BackupsHealth {
  count: number;
  last_backup_at: string | null;
}

export interface SystemHealthData {
  status: 'healthy' | 'warning';
  checked_at: string;
  database: {
    status: 'ok' | 'fail';
    driver: string;
  };
  scheduler: SchedulerHealth;
  queue: QueueHealth;
  failed_jobs: FailedJobsHealth;
  webhook: WebhookHealth;
  whatsapp: WhatsAppHealth;
  backups: BackupsHealth;
}

export interface SingleResponse<T> {
  data: T;
  meta?: unknown;
  message?: string | null;
}

export const healthService = {
  /**
   * Fetch comprehensive operational system health metrics.
   */
  async getSystemHealth(): Promise<SingleResponse<SystemHealthData>> {
    const res = await api.get<SingleResponse<SystemHealthData>>('/admin/health');
    return res.data;
  },

  /**
   * Retry a specific failed queue job.
   */
  async retryFailedJob(id: number): Promise<SingleResponse<null>> {
    const res = await api.post<SingleResponse<null>>(`/admin/health/failed-jobs/${id}/retry`);
    return res.data;
  },

  /**
   * Delete a specific failed queue job.
   */
  async deleteFailedJob(id: number): Promise<SingleResponse<null>> {
    const res = await api.delete<SingleResponse<null>>(`/admin/health/failed-jobs/${id}`);
    return res.data;
  },

  /**
   * Retry all failed queue jobs.
   */
  async retryAllFailedJobs(): Promise<SingleResponse<null>> {
    const res = await api.post<SingleResponse<null>>('/admin/health/failed-jobs/retry-all');
    return res.data;
  },

  /**
   * Flush/delete all failed queue jobs.
   */
  async flushFailedJobs(): Promise<SingleResponse<null>> {
    const res = await api.delete<SingleResponse<null>>('/admin/health/failed-jobs/flush');
    return res.data;
  },
};

export default healthService;
