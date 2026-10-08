import api from '@/lib/api';
import type {
  Question,
  CreateQuestionPayload,
  UpdateQuestionPayload,
} from '@/types/question';

export const questionService = {
  /**
   * Get list of all questions.
   */
  async getQuestions(): Promise<Question[]> {
    const res = await api.get<{ data: Question[] }>('/questions');
    return res.data.data;
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
    return res.data.data;
  },

  /**
   * Update an existing question (creates new version if text/type/options changed).
   */
  async updateQuestion(id: number, payload: UpdateQuestionPayload): Promise<Question> {
    const res = await api.put<{ data: Question }>(`/questions/${id}`, payload);
    return res.data.data;
  },

  /**
   * Toggle active status.
   */
  async toggleActive(id: number): Promise<{ id: number; is_active: boolean }> {
    const res = await api.patch<{ data: { id: number; is_active: boolean } }>(
      `/questions/${id}/toggle-active`
    );
    return res.data.data;
  },

  /**
   * Reorder questions.
   */
  async reorderQuestions(orders: Array<{ id: number; sort_order: number }>): Promise<void> {
    await api.post('/questions/reorder', { orders });
  },

  /**
   * Deactivate a question.
   */
  async deleteQuestion(id: number): Promise<void> {
    await api.delete(`/questions/${id}`);
  },
};
