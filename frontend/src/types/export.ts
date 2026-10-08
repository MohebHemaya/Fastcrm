export type ExportStatus = 'queued' | 'processing' | 'done' | 'failed';

export type ExportType = 'drivers' | 'work_order' | 'group_performance';

export interface ExportItem {
  id: number;
  user_id: number;
  type: ExportType;
  params: Record<string, any>;
  status: ExportStatus;
  path: string | null;
  error: string | null;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateExportParams {
  type: ExportType;
  params?: Record<string, any>;
}
