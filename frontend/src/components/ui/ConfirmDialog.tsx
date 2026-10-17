import React from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import {
  ExclamationTriangleIcon,
  ExclamationCircleIcon,
  InformationCircleIcon,
  CheckCircleIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';

export interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
  title: string;
  message?: string;
  children?: React.ReactNode;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'primary' | 'warning' | 'success';
  confirmIcon?: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  isLoading?: boolean;
}

export const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  children,
  confirmText = ar.common.confirm,
  cancelText = ar.common.cancel,
  variant = 'primary',
  confirmIcon,
  size = 'md',
  isLoading = false,
}) => {
  const getIcon = () => {
    switch (variant) {
      case 'danger':
        return <ExclamationCircleIcon className="h-6 w-6 text-danger shrink-0" />;
      case 'warning':
        return <ExclamationTriangleIcon className="h-6 w-6 text-warning shrink-0" />;
      case 'success':
        return <CheckCircleIcon className="h-6 w-6 text-success shrink-0" />;
      default:
        return <InformationCircleIcon className="h-6 w-6 text-primary shrink-0" />;
    }
  };

  const getConfirmButtonColor = () => {
    switch (variant) {
      case 'danger':
        return 'danger';
      case 'warning':
        return 'warning';
      case 'success':
        return 'success';
      default:
        return 'primary';
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="center"
      size={size}
      classNames={{
        base: 'border border-default-200 bg-content1 text-foreground rtl',
        header: 'border-b border-default-100',
        footer: 'border-t border-default-100',
      }}
    >
      <ModalContent>
        {() => (
          <>
            <ModalHeader className="flex items-center gap-3">
              {getIcon()}
              <span className="text-lg font-bold">{title}</span>
            </ModalHeader>
            <ModalBody className="py-4 space-y-3">
              {message && <p className="text-sm text-default-600 leading-relaxed">{message}</p>}
              {children}
            </ModalBody>
            <ModalFooter className="flex flex-col-reverse sm:flex-row gap-2 w-full safe-bottom border-t border-default-100">
              <Button
                variant="flat"
                color="default"
                className="w-full sm:w-auto min-h-[44px]"
                onPress={onClose}
                isDisabled={isLoading}
              >
                {cancelText}
              </Button>
              <Button
                color={getConfirmButtonColor()}
                className="w-full sm:w-auto min-h-[44px] font-bold"
                onPress={onConfirm}
                isLoading={isLoading}
                startContent={confirmIcon}
              >
                {confirmText}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default ConfirmDialog;
