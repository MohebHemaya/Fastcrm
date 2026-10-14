import { api, type ApiEnvelope } from '@/lib/api';
import type { PaginatedResponse } from '@/services/activityLogService';
import type {
  Driver,
  DriverFilterParams,
  CreateDriverPayload,
  UpdateDriverPayload,
  ChangeDriverStatusPayload,
  WhatsappGroup,
} from '@/types/driver';

export const driverService = {
  /**
   * Get paginated drivers list with search and filters
   */
  async getDrivers(params?: DriverFilterParams): Promise<PaginatedResponse<Driver>> {
    const response = await api.get<PaginatedResponse<Driver>>('/drivers', { params });
    return response.data;
  },

  /**
   * Get detailed driver profile with status histories and WhatsApp groups
   */
  async getDriver(id: number): Promise<ApiEnvelope<Driver>> {
    const response = await api.get<ApiEnvelope<Driver>>(`/drivers/${id}`);
    return response.data;
  },

  /**
   * Create a driver manually
   */
  async createDriver(payload: CreateDriverPayload): Promise<ApiEnvelope<Driver>> {
    const response = await api.post<ApiEnvelope<Driver>>('/drivers', payload);
    return response.data;
  },

  /**
   * Update driver info (name, notes)
   */
  async updateDriver(id: number, payload: UpdateDriverPayload): Promise<ApiEnvelope<Driver>> {
    const response = await api.patch<ApiEnvelope<Driver>>(`/drivers/${id}`, payload);
    return response.data;
  },

  /**
   * Change driver status through the state machine
   */
  async changeStatus(id: number, payload: ChangeDriverStatusPayload): Promise<ApiEnvelope<Driver>> {
    const response = await api.post<ApiEnvelope<Driver>>(`/drivers/${id}/status`, payload);
    return response.data;
  },

  /**
   * List all WhatsApp groups with driver count
   */
  async getWhatsappGroups(): Promise<ApiEnvelope<WhatsappGroup[]>> {
    const response = await api.get<ApiEnvelope<WhatsappGroup[]>>('/whatsapp-groups');
    return response.data;
  },

  /**
   * Get driver questionnaire answers and history
   */
  async getDriverAnswers(id: number): Promise<import('@/types/question').DriverAnswersResponse> {
    const response = await api.get<{ data: import('@/types/question').DriverAnswersResponse }>(
      `/drivers/${id}/answers`
    );
    return response.data.data;
  },

  /**
   * Manually update a driver answer
   */
  async updateDriverAnswer(
    driverId: number,
    answerId: number,
    payload: {
      value_text?: string;
      value_number?: number;
      option_id?: number;
      is_skipped?: boolean;
    }
  ): Promise<void> {
    await api.put(`/drivers/${driverId}/answers/${answerId}`, payload);
  },

  /**
   * Admin action: Resend current unanswered question to driver
   */
  async resendQuestion(driverId: number): Promise<void> {
    await api.post(`/drivers/${driverId}/resend-question`);
  },

  /**
   * Admin action: Request answer update from active driver
   */
  async requestAnswerUpdate(driverId: number): Promise<void> {
    await api.post(`/drivers/${driverId}/request-answer-update`);
  },

  /**
   * Delete a driver (soft delete)
   */
  async deleteDriver(id: number): Promise<ApiEnvelope<null>> {
    const response = await api.delete<ApiEnvelope<null>>(`/drivers/${id}`);
    return response.data;
  },
};

export default driverService;
