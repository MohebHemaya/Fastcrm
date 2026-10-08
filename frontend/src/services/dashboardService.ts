import { api } from '@/lib/api';
import type {
  DashboardSummary,
  GroupPerformance,
  DashboardFilterParams,
} from '@/types/dashboard';

export interface SingleResponse<T> {
  data: T;
  meta?: unknown;
  message?: string | null;
}

export const dashboardService = {
  /**
   * Fetch aggregated dashboard summary metrics (cached ~5 minutes on backend).
   */
  async getSummary(params?: DashboardFilterParams): Promise<SingleResponse<DashboardSummary>> {
    const res = await api.get<SingleResponse<DashboardSummary>>('/dashboard/summary', { params });
    return res.data;
  },

  /**
   * Fetch WhatsApp group recruitment & conversion performance metrics.
   */
  async getGroupPerformance(params?: DashboardFilterParams): Promise<SingleResponse<GroupPerformance[]>> {
    const res = await api.get<SingleResponse<GroupPerformance[]>>('/dashboard/group-performance', { params });
    return res.data;
  },
};

export default dashboardService;
