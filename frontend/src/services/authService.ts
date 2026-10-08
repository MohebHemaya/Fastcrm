import { api, getCsrfCookie, type ApiEnvelope } from '@/lib/api';
import type { User } from '@/lib/stores/useAuthStore';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface UpdatePasswordData {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export const authService = {
  async login(credentials: LoginCredentials): Promise<User> {
    await getCsrfCookie();
    const response = await api.post<ApiEnvelope<User>>('/auth/login', credentials);
    return response.data.data;
  },

  async logout(): Promise<void> {
    await api.post<ApiEnvelope<null>>('/auth/logout');
  },

  async me(): Promise<User> {
    const response = await api.get<ApiEnvelope<User>>('/auth/me');
    return response.data.data;
  },

  async updatePassword(data: UpdatePasswordData): Promise<void> {
    await api.patch<ApiEnvelope<null>>('/auth/password', data);
  },
};

export default authService;
