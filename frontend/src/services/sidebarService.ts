import { api } from '@/lib/api';
import appCache from '@/lib/cache';

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
   * Utilizes client-side SWR caching (15s) to eliminate duplicate requests.
   */
  async getBadges(forceRefresh = false): Promise<SidebarBadges> {
    if (forceRefresh) {
      appCache.invalidate('sidebar:badges');
    }
    const { data } = await appCache.swr(
      'sidebar:badges',
      async () => {
        const res = await api.get<SidebarBadgesResponse>('/sidebar-badges');
        return res.data.data;
      },
      { ttlMs: 15_000 }
    );
    return data;
  },
};

export default sidebarService;
