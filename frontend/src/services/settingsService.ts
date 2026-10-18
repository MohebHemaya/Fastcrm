import api from '@/lib/api';
import appCache from '@/lib/cache';
import type { SettingsResponse, SystemSettings } from '@/types/settings';

export const settingsService = {
  /**
   * Get all system settings (cached with SWR for snappy navigation).
   */
  async getSettings(forceRefresh = false): Promise<SettingsResponse> {
    if (forceRefresh) {
      appCache.invalidate('system:settings');
    }
    const { data } = await appCache.swr(
      'system:settings',
      async () => {
        const res = await api.get<{ data: SettingsResponse }>('/settings');
        return res.data.data;
      },
      { ttlMs: 60_000 }
    );
    return data;
  },

  /**
   * Update system settings.
   */
  async updateSettings(settings: Partial<SystemSettings>): Promise<void> {
    await api.put('/settings', { settings });
    appCache.invalidate('system:settings');
  },
};

export default settingsService;
