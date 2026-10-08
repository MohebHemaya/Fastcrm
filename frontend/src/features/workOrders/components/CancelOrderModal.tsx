import React, { useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Textarea,
} from '@heroui/react';
import { ExclamationCircleIcon } from '@/components/icons';
import { ar } from '@/i18n/ar';

interface CancelOrderModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (reason: string) => Promise<void> | void;
  isLoading?: boolean;
}

export const CancelOrderModal: React.FC<CancelOrderModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  isLoading = false,
}) => {
  const [reason, setReason] = useState('');

  const handleConfirm = async () => {
    await onConfirm(reason);
    setReason('');
  };

  const handleClose = () => {
    if (!isLoading) {
      setReason('');
      onClose();
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      size="md"
      classNames={{
        base: 'border border-default-200 bg-content1 text-foreground rtl',
        header: 'border-b border-default-100',
        footer: 'border-t border-default-100',
      }}
    >
      <ModalContent>
        {() => (
          <>
            <ModalHeader className="flex items-center gap-2 text-danger">
              <ExclamationCircleIcon className="w-5 h-5 text-danger" />
              <span>{ar.workOrders.cancelModal.title}</span>
            </ModalHeader>
            <ModalBody className="space-y-4 py-4 text-start">
              <p className="text-sm text-default-600">
                {ar.workOrders.cancelModal.message}
              </p>

              <Textarea
                label={ar.workOrders.cancelModal.reasonLabel}
                placeholder={ar.workOrders.cancelModal.reasonPlaceholder}
                variant="bordered"
                labelPlacement="outside"
                value={reason}
                onValueChange={setReason}
                minRows={3}
                isDisabled={isLoading}
              />
            </ModalBody>
            <ModalFooter className="flex justify-end gap-2">
              <Button
                variant="flat"
                onPress={handleClose}
                isDisabled={isLoading}
              >
                {ar.common.cancel}
              </Button>
              <Button
                color="danger"
                onPress={handleConfirm}
                isLoading={isLoading}
              >
                {ar.workOrders.cancelModal.confirmButton}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default CancelOrderModal;
