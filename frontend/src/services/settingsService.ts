import api from '@/lib/api';
import type { SettingsResponse, SystemSettings } from '@/types/settings';

export const settingsService = {
  /**
   * Get all system settings.
   */
  async getSettings(): Promise<SettingsResponse> {
    const res = await api.get<{ data: SettingsResponse }>('/settings');
    return res.data.data;
  },

  /**
   * Update system settings.
   */
  async updateSettings(settings: Partial<SystemSettings>): Promise<void> {
    await api.put('/settings', { settings });
  },
};
