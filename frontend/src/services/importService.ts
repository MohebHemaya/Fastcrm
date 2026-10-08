import { api, type ApiEnvelope } from '@/lib/api';
import type { PaginatedResponse } from '@/services/activityLogService';
import type {
  ImportItem,
  ImportRow,
  ImportRowsFilterParams,
} from '@/types/import';

export const importService = {
  /**
   * Upload an Excel/CSV file to start the import process
   */
  async uploadImport(file: File): Promise<ApiEnvelope<ImportItem>> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post<ApiEnvelope<ImportItem>>('/imports', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  /**
   * Get import status and counters
   */
  async getImport(id: number): Promise<ApiEnvelope<ImportItem>> {
    const response = await api.get<ApiEnvelope<ImportItem>>(`/imports/${id}`);
    return response.data;
  },

  /**
   * Get staged rows for an import with classification/search filters
   */
  async getImportRows(id: number, params?: ImportRowsFilterParams): Promise<PaginatedResponse<ImportRow>> {
    const response = await api.get<PaginatedResponse<ImportRow>>(`/imports/${id}/rows`, { params });
    return response.data;
  },

  /**
   * Commit staged import to create new drivers and attach groups
   */
  async commitImport(id: number): Promise<ApiEnvelope<ImportItem>> {
    const response = await api.post<ApiEnvelope<ImportItem>>(`/imports/${id}/commit`);
    return response.data;
  },

  /**
   * Cancel staged import before commit
   */
  async cancelImport(id: number): Promise<ApiEnvelope<ImportItem>> {
    const response = await api.post<ApiEnvelope<ImportItem>>(`/imports/${id}/cancel`);
    return response.data;
  },

  /**
   * Get list of imports (e.g. pending/ready_for_review)
   */
  async getImports(params?: { status?: string; per_page?: number }): Promise<PaginatedResponse<ImportItem>> {
    const response = await api.get<PaginatedResponse<ImportItem>>('/imports', { params });
    return response.data;
  },

  /**
   * Delete an import and its associated staged rows
   */
  async deleteImport(id: number): Promise<ApiEnvelope<null>> {
    const response = await api.delete<ApiEnvelope<null>>(`/imports/${id}`);
    return response.data;
  },

  /**
   * Get URL to download Excel/CSV template
   */
  getTemplateUrl(): string {
    return '/api/v1/imports/template';
  },

  /**
   * Get URL to download invalid rows CSV
   */
  getExportInvalidUrl(id: number): string {
    return `/api/v1/imports/${id}/export-invalid`;
  },
};

export default importService;
