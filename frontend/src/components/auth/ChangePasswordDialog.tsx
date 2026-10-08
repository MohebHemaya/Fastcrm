import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import { EyeIcon, EyeSlashIcon, KeyIcon } from '@/components/icons';
import { FormInput } from '@/components/form';
import { authService, type UpdatePasswordData } from '@/services/authService';
import { useUiStore } from '@/lib/stores/useUiStore';
import { type ApiValidationError } from '@/lib/api';
import { ar } from '@/i18n/ar';
import { useIsMobile } from '@/hooks/useMediaQuery';

export interface ChangePasswordDialogProps {
  isOpen: boolean;
  onClose: () => void;
}

export const ChangePasswordDialog: React.FC<ChangePasswordDialogProps> = ({
  isOpen,
  onClose,
}) => {
  const isMobile = useIsMobile();
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const addToast = useUiStore((state) => state.addToast);

  const {
    control,
    handleSubmit,
    setError,
    reset,
  } = useForm<UpdatePasswordData>({
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  });

  const handleClose = () => {
    reset();
    onClose();
  };

  const onSubmit = async (data: UpdatePasswordData) => {
    setIsSubmitting(true);
    try {
      await authService.updatePassword(data);
      addToast({
        type: 'success',
        message: ar.auth.passwordChanged,
      });
      handleClose();
    } catch (err: any) {
      const apiErr = err as ApiValidationError;
      if (apiErr.fieldErrors) {
        for (const [field, messages] of Object.entries(apiErr.fieldErrors)) {
          if (['current_password', 'password', 'password_confirmation'].includes(field)) {
            setError(field as keyof UpdatePasswordData, {
              type: 'server',
              message: messages[0],
            });
          }
        }
      } else {
        addToast({
          type: 'error',
          message: err?.response?.data?.message || ar.errors.generalError,
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      placement="center"
      size={isMobile ? 'full' : 'md'}
      scrollBehavior="inside"
      classNames={{
        base: isMobile
          ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none bg-content1 text-foreground rtl'
          : 'border border-default-200 bg-content1 text-foreground rtl',
        header: 'border-b border-default-100',
        footer: 'border-t border-default-100',
      }}
    >
      <ModalContent>
        {() => (
          <form onSubmit={handleSubmit(onSubmit)}>
            <ModalHeader className="flex items-center gap-3">
              <KeyIcon className="h-6 w-6 text-primary" />
              <div>
                <h3 className="text-lg font-bold text-default-900">{ar.auth.changePassword}</h3>
              </div>
            </ModalHeader>

            <ModalBody className="flex flex-col gap-4 py-5">
              <FormInput
                name="current_password"
                control={control}
                label={ar.auth.currentPassword}
                type={showCurrent ? 'text' : 'password'}
                variant="bordered"
                labelPlacement="outside"
                rules={{
                  required: ar.auth.currentPasswordRequired,
                }}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowCurrent(!showCurrent)}
                    className="focus:outline-none text-default-400 hover:text-default-600 min-h-[44px] min-w-[44px] flex items-center justify-center -me-2"
                    aria-label={showCurrent ? ar.auth.hidePassword : ar.auth.showPassword}
                  >
                    {showCurrent ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                }
              />

              <FormInput
                name="password"
                control={control}
                label={ar.auth.newPassword}
                type={showNew ? 'text' : 'password'}
                variant="bordered"
                labelPlacement="outside"
                rules={{
                  required: ar.auth.newPasswordRequired,
                  minLength: {
                    value: 8,
                    message: ar.auth.passwordMinLength,
                  },
                }}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowNew(!showNew)}
                    className="focus:outline-none text-default-400 hover:text-default-600 min-h-[44px] min-w-[44px] flex items-center justify-center -me-2"
                    aria-label={showNew ? ar.auth.hidePassword : ar.auth.showPassword}
                  >
                    {showNew ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                }
              />

              <FormInput
                name="password_confirmation"
                control={control}
                label={ar.auth.confirmPassword}
                type={showConfirm ? 'text' : 'password'}
                variant="bordered"
                labelPlacement="outside"
                rules={{
                  required: ar.auth.newPasswordRequired,
                  validate: (value, formValues) =>
                    value === formValues.password || ar.auth.passwordsDoNotMatch,
                }}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowConfirm(!showConfirm)}
                    className="focus:outline-none text-default-400 hover:text-default-600 min-h-[44px] min-w-[44px] flex items-center justify-center -me-2"
                    aria-label={showConfirm ? ar.auth.hidePassword : ar.auth.showPassword}
                  >
                    {showConfirm ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                }
              />
            </ModalBody>

            <ModalFooter className="flex flex-col-reverse sm:flex-row gap-2 w-full safe-bottom">
              <Button
                variant="flat"
                color="default"
                className="w-full sm:w-auto min-h-[44px]"
                onPress={handleClose}
                isDisabled={isSubmitting}
              >
                {ar.common.cancel}
              </Button>
              <Button
                type="submit"
                color="primary"
                className="w-full sm:w-auto min-h-[44px]"
                isLoading={isSubmitting}
              >
                {ar.common.save}
              </Button>
            </ModalFooter>
          </form>
        )}
      </ModalContent>
    </Modal>
  );
};

export default ChangePasswordDialog;
