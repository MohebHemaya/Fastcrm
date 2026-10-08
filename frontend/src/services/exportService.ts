import { api } from '@/lib/api';
import type {
  ExportItem,
  CreateExportParams,
} from '@/types/export';

export interface SingleResponse<T> {
  data: T;
  meta?: unknown;
  message?: string | null;
}

export const exportService = {
  /**
   * Request a new queued export job (drivers, work_order, group_performance).
   */
  async requestExport(payload: CreateExportParams): Promise<SingleResponse<ExportItem>> {
    const res = await api.post<SingleResponse<ExportItem>>('/exports', payload);
    return res.data;
  },

  /**
   * List recent exports for the current user.
   */
  async getExports(): Promise<SingleResponse<ExportItem[]>> {
    const res = await api.get<SingleResponse<ExportItem[]>>('/exports');
    return res.data;
  },

  /**
   * Get single export status.
   */
  async getExport(id: number): Promise<SingleResponse<ExportItem>> {
    const res = await api.get<SingleResponse<ExportItem>>(`/exports/${id}`);
    return res.data;
  },

  /**
   * Download a completed export file using authenticated blob request.
   */
  async downloadExport(id: number, filename?: string): Promise<void> {
    const response = await api.get(`/exports/${id}/download`, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data]);
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', filename || `export_${id}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  /**
   * Download work order PDF directly.
   */
  async downloadWorkOrderPdf(id: number, code: string): Promise<void> {
    const response = await api.get(`/work-orders/${id}/pdf`, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'application/pdf' });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', `work_order_${code}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  /**
   * Download driver profile PDF directly.
   */
  async downloadDriverPdf(id: number, name?: string): Promise<void> {
    const response = await api.get(`/drivers/${id}/pdf`, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'application/pdf' });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', name ? `driver_${name.replace(/\s+/g, '_')}_${id}.pdf` : `driver_profile_${id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },
};

export default exportService;
