import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useLocation } from 'react-router-dom';
import { Button, Card, CardBody, CardHeader } from '@heroui/react';
import {
  EnvelopeIcon,
  LockClosedIcon,
  EyeIcon,
  EyeSlashIcon,
  ExclamationCircleIcon,
} from '@/components/icons';
import { FormInput } from '@/components/form';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { useUiStore } from '@/lib/stores/useUiStore';
import { type LoginCredentials } from '@/services/authService';
import { type ApiValidationError } from '@/lib/api';
import ToastContainer from '@/components/ui/ToastContainer';
import { ar } from '@/i18n/ar';

export const LoginPage: React.FC = () => {
  const [showPassword, setShowPassword] = useState(false);
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { login } = useAuthStore();
  const addToast = useUiStore((state) => state.addToast);
  const navigate = useNavigate();
  const location = useLocation();

  const from = (location.state as any)?.from?.pathname || '/';

  const {
    control,
    handleSubmit,
    setError,
  } = useForm<LoginCredentials>({
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const onSubmit = async (data: LoginCredentials) => {
    setIsSubmitting(true);
    setGeneralError(null);

    try {
      await login(data);
      addToast({
        type: 'success',
        message: ar.auth.loginSuccess,
      });
      navigate(from, { replace: true });
    } catch (err: any) {
      const apiErr = err as ApiValidationError;
      const status = apiErr?.response?.status;
      const responseData = apiErr?.response?.data as any;

      if (status === 422 && apiErr.fieldErrors) {
        for (const [field, messages] of Object.entries(apiErr.fieldErrors)) {
          if (field === 'email' || field === 'password') {
            setError(field, {
              type: 'server',
              message: messages[0],
            });
          }
        }
        const errorMsg =
          responseData?.message ||
          (apiErr.fieldErrors.email ? apiErr.fieldErrors.email[0] : null) ||
          ar.errors.generalError;
        setGeneralError(errorMsg);
      } else if (status === 403) {
        setGeneralError(responseData?.message || 'الحساب معطل، يرجى التواصل مع الإدارة.');
      } else if (status === 429) {
        setGeneralError(ar.errors.rateLimit);
      } else {
        const errorMsg =
          responseData?.message ||
          (responseData?.errors?.email ? responseData.errors.email[0] : null) ||
          ar.errors.generalError;
        setGeneralError(errorMsg);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-[100dvh] w-full items-center justify-center bg-default-50 p-4 safe-top safe-bottom">
      <ToastContainer />

      <Card className="w-full max-w-md border border-default-200 bg-content1 shadow-none">
        <CardHeader className="flex flex-col items-center gap-3 pb-2 pt-8 text-center">
          <img src="/logo.png" alt={ar.app.title} className="h-16 sm:h-20 w-auto object-contain" />
          <h1 className="sr-only">
            {ar.app.title}
          </h1>
          <p className="text-sm text-default-500 font-medium">
            {ar.auth.loginPrompt}
          </p>
        </CardHeader>

        <CardBody className="px-6 py-6 sm:px-8">
          {generalError && (
            <div
              data-testid="login-general-error"
              className="mb-5 flex items-center gap-3 rounded-lg border border-danger-200 bg-danger-50 p-3.5 text-sm text-danger-700"
            >
              <ExclamationCircleIcon className="h-5 w-5 shrink-0 text-danger-600" />
              <p className="flex-1 text-start leading-tight">{generalError}</p>
            </div>
          )}

          <form noValidate onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <FormInput
              name="email"
              control={control}
              label={ar.auth.email}
              type="email"
              autoComplete="email"
              placeholder="admin@example.com"
              variant="bordered"
              labelPlacement="outside"
              startContent={<EnvelopeIcon className="h-4 w-4 text-default-400" />}
              rules={{
                required: ar.auth.emailRequired,
                pattern: {
                  value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                  message: ar.auth.emailInvalid,
                },
              }}
            />

            <FormInput
              name="password"
              control={control}
              label={ar.auth.password}
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              placeholder="••••••••"
              variant="bordered"
              labelPlacement="outside"
              startContent={<LockClosedIcon className="h-4 w-4 text-default-400" />}
              endContent={
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="focus:outline-none text-default-400 hover:text-default-600 min-w-[44px] min-h-[44px] flex items-center justify-center -me-2"
                  aria-label={showPassword ? ar.auth.hidePassword : ar.auth.showPassword}
                >
                  {showPassword ? (
                    <EyeSlashIcon className="h-4 w-4" />
                  ) : (
                    <EyeIcon className="h-4 w-4" />
                  )}
                </button>
              }
              rules={{
                required: ar.auth.passwordRequired,
              }}
            />

            <Button
              type="submit"
              color="primary"
              size="lg"
              className="mt-3 font-semibold shadow-none"
              isLoading={isSubmitting}
            >
              {isSubmitting ? ar.auth.loggingIn : ar.auth.login}
            </Button>
          </form>
        </CardBody>
      </Card>
    </div>
  );
};

export default LoginPage;
