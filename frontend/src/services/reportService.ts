import { api, type ApiEnvelope } from '@/lib/api';

export interface ReportDateParams {
  start_date?: string;
  end_date?: string;
}

export interface ReportQueryParams extends ReportDateParams {
  search?: string;
  status?: string;
  direction?: string;
  group_id?: number | string;
  question_id?: number | string;
  is_skipped?: boolean | string;
  page?: number;
  per_page?: number;
}

export interface ReportItem {
  id: string;
  title: string;
  description: string;
  badge: string;
  icon: string;
}

export interface ReportColumn {
  key: string;
  label: string;
  align?: 'start' | 'center' | 'end';
}

export interface ReportTableData {
  columns: ReportColumn[];
  rows: Array<Record<string, any>>;
  summary: Record<string, string | number>;
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export const reportService = {
  /**
   * Get list of available operational reports.
   */
  async getReportList(): Promise<ReportItem[]> {
    const res = await api.get<ApiEnvelope<ReportItem[]>>('/reports/list');
    return res.data.data;
  },

  /**
   * Get paginated table rows, columns, and real KPI metrics for a specific report.
   */
  async getReportData(reportType: string, params?: ReportQueryParams): Promise<ReportTableData> {
    const res = await api.get<any>(`/reports/data/${reportType}`, { params });
    return {
      columns: res.data.columns || [],
      rows: res.data.data || [],
      summary: res.data.summary || {},
      meta: res.data.meta || { current_page: 1, per_page: 25, total: 0, last_page: 1 },
    };
  },

  /**
   * Download Excel (.xlsx) file directly for the given report and filter params.
   */
  async downloadReport(reportType: string, params?: ReportQueryParams, filename?: string): Promise<void> {
    const response = await api.get(`/reports/download/${reportType}`, {
      params,
      responseType: 'blob',
    });

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', filename || `report_${reportType}_${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },
};

export default reportService;
