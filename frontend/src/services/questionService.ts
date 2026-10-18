import api from '@/lib/api';
import appCache from '@/lib/cache';
import type {
  Question,
  CreateQuestionPayload,
  UpdateQuestionPayload,
} from '@/types/question';

export const questionService = {
  /**
   * Get list of all questions (with SWR caching for instant page loads).
   */
  async getQuestions(forceRefresh = false): Promise<Question[]> {
    if (forceRefresh) {
      appCache.invalidate('questions:list');
    }
    const { data } = await appCache.swr(
      'questions:list',
      async () => {
        const res = await api.get<{ data: Question[] }>('/questions');
        return res.data.data;
      },
      { ttlMs: 30_000 }
    );
    return data;
  },

  /**
   * Get single question with version history.
   */
  async getQuestion(id: number): Promise<Question> {
    const res = await api.get<{ data: Question }>(`/questions/${id}`);
    return res.data.data;
  },

  /**
   * Create a new question.
   */
  async createQuestion(payload: CreateQuestionPayload): Promise<Question> {
    const res = await api.post<{ data: Question }>('/questions', payload);
    appCache.invalidate('questions:');
    return res.data.data;
  },

  /**
   * Update an existing question (creates new version if text/type/options changed).
   */
  async updateQuestion(id: number, payload: UpdateQuestionPayload): Promise<Question> {
    const res = await api.put<{ data: Question }>(`/questions/${id}`, payload);
    appCache.invalidate('questions:');
    return res.data.data;
  },

  /**
   * Toggle active status.
   */
  async toggleActive(id: number): Promise<{ id: number; is_active: boolean }> {
    const res = await api.patch<{ data: { id: number; is_active: boolean } }>(
      `/questions/${id}/toggle-active`
    );
    appCache.invalidate('questions:');
    return res.data.data;
  },

  /**
   * Reorder questions.
   */
  async reorderQuestions(orders: Array<{ id: number; sort_order: number }>): Promise<void> {
    await api.post('/questions/reorder', { orders });
    appCache.invalidate('questions:');
  },

  /**
   * Deactivate a question.
   */
  async deleteQuestion(id: number): Promise<void> {
    await api.delete(`/questions/${id}`);
    appCache.invalidate('questions:');
  },

  /**
   * Send test WhatsApp poll/message for this question to a phone number.
   */
  async testSend(id: number, phone: string): Promise<{ provider_message_id: string; phone: string }> {
    const res = await api.post<{ data: { provider_message_id: string; phone: string } }>(
      `/questions/${id}/test-send`,
      { phone }
    );
    return res.data.data;
  },
};

export default questionService;
