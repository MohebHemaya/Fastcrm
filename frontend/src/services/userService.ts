import { api, type ApiEnvelope } from '@/lib/api';
import type { User, CreateUserData, UpdateUserData, UserFilterParams } from '@/types/user';

export interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedResponse<T> extends ApiEnvelope<T[]> {
  meta: PaginatedMeta;
}

export const userService = {
  /**
   * Get paginated list of users
   */
  async getUsers(params?: UserFilterParams): Promise<PaginatedResponse<User>> {
    const res = await api.get<PaginatedResponse<User>>('/users', {
      params,
    });
    return res.data;
  },

  /**
   * Get single user details
   */
  async getUser(id: number): Promise<ApiEnvelope<User>> {
    const res = await api.get<ApiEnvelope<User>>(`/users/${id}`);
    return res.data;
  },

  /**
   * Create a new user
   */
  async createUser(data: CreateUserData): Promise<ApiEnvelope<User>> {
    const res = await api.post<ApiEnvelope<User>>('/users', data);
    return res.data;
  },

  /**
   * Update an existing user
   */
  async updateUser(id: number, data: UpdateUserData): Promise<ApiEnvelope<User>> {
    const res = await api.put<ApiEnvelope<User>>(`/users/${id}`, data);
    return res.data;
  },

  /**
   * Toggle user active status
   */
  async toggleActive(id: number): Promise<ApiEnvelope<User>> {
    const res = await api.patch<ApiEnvelope<User>>(`/users/${id}/toggle-active`);
    return res.data;
  },

  /**
   * Delete user
   */
  async deleteUser(id: number): Promise<ApiEnvelope<null>> {
    const res = await api.delete<ApiEnvelope<null>>(`/users/${id}`);
    return res.data;
  },
};

export default userService;
