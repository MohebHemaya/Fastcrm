import { create } from 'zustand';
import { authService, type LoginCredentials } from '@/services/authService';

export interface User {
  id: number;
  name: string;
  email: string;
  is_active?: boolean;
  last_login_at?: string | null;
  created_at?: string;
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  isInitialized: boolean;
  setUser: (user: User | null) => void;
  setLoading: (isLoading: boolean) => void;
  clearAuth: () => void;
  login: (credentials: LoginCredentials) => Promise<User>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  isAuthenticated: false,
  isLoading: true,
  isInitialized: false,

  setUser: (user) =>
    set({
      user,
      isAuthenticated: !!user,
      isLoading: false,
    }),

  setLoading: (isLoading) => set({ isLoading }),

  clearAuth: () =>
    set({
      user: null,
      isAuthenticated: false,
      isLoading: false,
      isInitialized: true,
    }),

  login: async (credentials: LoginCredentials) => {
    try {
      const user = await authService.login(credentials);
      set({
        user,
        isAuthenticated: true,
        isLoading: false,
        isInitialized: true,
      });
      return user;
    } catch (error) {
      throw error;
    }
  },

  logout: async () => {
    try {
      if (get().isAuthenticated) {
        await authService.logout();
      }
    } catch {
      // Ignored if session was already destroyed
    } finally {
      get().clearAuth();
    }
  },

  checkAuth: async () => {
    set({ isLoading: true });
    try {
      const user = await authService.me();
      set({
        user,
        isAuthenticated: true,
        isLoading: false,
        isInitialized: true,
      });
    } catch {
      set({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        isInitialized: true,
      });
    }
  },
}));

export default useAuthStore;
