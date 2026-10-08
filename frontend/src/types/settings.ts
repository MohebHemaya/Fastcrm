export interface SystemSettings {
  questionnaire_reminder_hours: number;
  questionnaire_timeout_days: number;
  send_rate_per_minute: number;
  invite_batch_size: number;
  invite_no_response_days: number;
}

export interface SettingDefinition {
  key: string;
  value: any;
  type: string;
  description_ar: string;
}

export interface SettingsResponse {
  settings: SystemSettings;
  definitions: SettingDefinition[];
}
