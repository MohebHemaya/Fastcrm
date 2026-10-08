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
} from '@/components/icons';
import { ar } from '@/i18n/ar';

export interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'primary' | 'warning';
  isLoading?: boolean;
}

export const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = ar.common.confirm,
  cancelText = ar.common.cancel,
  variant = 'primary',
  isLoading = false,
}) => {
  const getIcon = () => {
    switch (variant) {
      case 'danger':
        return <ExclamationCircleIcon className="h-6 w-6 text-danger" />;
      case 'warning':
        return <ExclamationTriangleIcon className="h-6 w-6 text-warning" />;
      default:
        return <InformationCircleIcon className="h-6 w-6 text-primary" />;
    }
  };

  const getConfirmButtonColor = () => {
    switch (variant) {
      case 'danger':
        return 'danger';
      case 'warning':
        return 'warning';
      default:
        return 'primary';
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="center"
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
            <ModalBody className="py-4">
              <p className="text-sm text-default-600 leading-relaxed">{message}</p>
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
                className="w-full sm:w-auto min-h-[44px]"
                onPress={onConfirm}
                isLoading={isLoading}
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
