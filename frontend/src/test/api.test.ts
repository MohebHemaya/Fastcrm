import { describe, it, expect, beforeEach } from 'vitest';
import { api, type ApiValidationError } from '@/lib/api';
import { useAuthStore } from '@/lib/stores/useAuthStore';

describe('Axios API Client Interceptors', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: { id: 1, name: 'Admin', email: 'admin@example.com' },
      isAuthenticated: true,
      isLoading: false,
    });
  });

  it('handles 401 by clearing auth state', async () => {
    try {
      await api.get('/test-401');
      expect.fail('Should have thrown 401 error');
    } catch (error: any) {
      expect(error.response?.status).toBe(401);
      const authState = useAuthStore.getState();
      expect(authState.isAuthenticated).toBe(false);
      expect(authState.user).toBeNull();
    }
  });

  it('handles 419 by refreshing CSRF cookie and retrying the request once', async () => {
    const response = await api.get('/test-419');
    expect(response.status).toBe(200);
    expect(response.data.data.retried).toBe(true);
  });

  it('handles 422 by normalizing validation errors into fieldErrors object', async () => {
    try {
      await api.post('/test-422', { phone: 'invalid' });
      expect.fail('Should have thrown 422 error');
    } catch (error: any) {
      const valError = error as ApiValidationError;
      expect(valError.response?.status).toBe(422);
      expect(valError.fieldErrors).toBeDefined();
      expect(valError.fieldErrors?.phone).toContain('رقم الهاتف غير صالح.');
      expect(valError.fieldErrors?.email).toContain('البريد الإلكتروني مطلوب.');
    }
  });
});
