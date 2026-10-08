import { api } from '@/lib/api';

export interface SidebarBadges {
  orders_open: number;
  drivers_needs_review: number;
  imports_ready: number;
  failed_messages_24h: number;
  health_warning: boolean;
  new_drivers?: number;
}

export interface SidebarBadgesResponse {
  data: SidebarBadges;
  meta?: unknown;
  message?: string | null;
}

export const sidebarService = {
  /**
   * Fetch live operational badge counts for sidebar navigation.
   * Cached <= 30 seconds on the backend.
   */
  async getBadges(): Promise<SidebarBadges> {
    const res = await api.get<SidebarBadgesResponse>('/sidebar-badges');
    return res.data.data;
  },
};

export default sidebarService;
