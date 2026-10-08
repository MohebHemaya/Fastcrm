import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from './stores/useAuthStore';
import { useUiStore } from './stores/useUiStore';

export interface ApiEnvelope<T = any> {
  data: T;
  meta: Record<string, any> | null;
  message: string | null;
}

export interface ApiValidationError extends AxiosError {
  fieldErrors?: Record<string, string[]>;
}

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

export const getCsrfCookie = async (): Promise<void> => {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
};

// Response interceptors
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };
    const status = error.response?.status;
    const responseData = error.response?.data as any;

    // 401 Unauthorized -> Clear auth state and redirect to login
    if (status === 401) {
      useAuthStore.getState().clearAuth();
      if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
      return Promise.reject(error);
    }

    // 419 CSRF Token Mismatch -> Refresh cookie and retry once
    if (status === 419 && originalRequest && !originalRequest._retry) {
      originalRequest._retry = true;
      try {
        await getCsrfCookie();
        return api(originalRequest);
      } catch (csrfError) {
        return Promise.reject(csrfError);
      }
    }

    // 422 Unprocessable Entity -> Normalize validation errors to { fieldErrors }
    if (status === 422) {
      const rawErrors = responseData?.errors || responseData?.meta?.errors || {};
      const normalizedErrors: Record<string, string[]> = {};

      for (const [key, value] of Object.entries(rawErrors)) {
        normalizedErrors[key] = Array.isArray(value) ? value.map(String) : [String(value)];
      }

      const validationError = error as ApiValidationError;
      validationError.fieldErrors = normalizedErrors;
      return Promise.reject(validationError);
    }

    // 429 Too Many Requests -> Toast
    if (status === 429) {
      useUiStore.getState().addToast({
        type: 'warning',
        message: 'تم تجاوز الحد المسموح من الطلبات، يرجى الانتظار قليلاً.',
      });
      return Promise.reject(error);
    }

    // 5xx Server Error -> Toast
    if (status && status >= 500) {
      useUiStore.getState().addToast({
        type: 'error',
        message: 'حدث خطأ غير متوقع في الخادم، يرجى المحاولة لاحقاً.',
      });
      return Promise.reject(error);
    }

    return Promise.reject(error);
  }
);

export default api;
