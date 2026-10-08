import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Textarea,
} from '@heroui/react';
import type { WorkOrderRecipient } from '@/types/workOrder';
import ar from '@/i18n/ar';

interface RemoveDriverModalProps {
  isOpen: boolean;
  onClose: () => void;
  recipient: WorkOrderRecipient | null;
  isConfirmed: boolean;
  onConfirm: (reason: string) => Promise<void>;
  isLoading?: boolean;
}

export const RemoveDriverModal: React.FC<RemoveDriverModalProps> = ({
  isOpen,
  onClose,
  recipient,
  isConfirmed,
  onConfirm,
  isLoading = false,
}) => {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (isOpen) {
      setReason('');
      setError('');
    }
  }, [isOpen]);

  const handleConfirm = async () => {
    if (!reason.trim()) {
      setError(ar.workOrders.removeDriverModal.reasonRequired);
      return;
    }
    setError('');
    await onConfirm(reason.trim());
  };

  if (!recipient) {
    return null;
  }

  const title = isConfirmed
    ? ar.workOrders.removeDriverModal.titleConfirmed
    : ar.workOrders.removeDriverModal.titleBackup;

  const message = isConfirmed
    ? ar.workOrders.removeDriverModal.messageConfirmed
    : ar.workOrders.removeDriverModal.messageBackup;

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md" placement="center">
      <ModalContent>
        {(closeModal) => (
          <>
            <ModalHeader className="flex flex-col gap-1 text-danger text-start">
              {title}
            </ModalHeader>
            <ModalBody className="text-start space-y-4">
              <p className="text-default-700">{message}</p>

              <div className="p-3 rounded-lg bg-default-100 border border-default-200">
                <p className="text-sm font-semibold text-default-900">
                  {recipient.driver_name}
                </p>
                <p className="text-xs text-default-500 font-mono">
                  {recipient.driver_phone}
                </p>
              </div>

              <Textarea
                label={ar.workOrders.removeDriverModal.reasonRequired}
                placeholder={ar.workOrders.removeDriverModal.reasonPlaceholder}
                variant="bordered"
                labelPlacement="outside"
                value={reason}
                onValueChange={(val) => {
                  setReason(val);
                  if (error) setError('');
                }}
                isInvalid={Boolean(error)}
                errorMessage={error}
                minRows={3}
                isRequired
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                color="default"
                onPress={closeModal}
                onClick={closeModal}
                disabled={isLoading}
              >
                {ar.common.cancel}
              </Button>
              <Button
                color="danger"
                onPress={handleConfirm}
                onClick={handleConfirm}
                isLoading={isLoading}
              >
                {ar.workOrders.removeDriverModal.confirm}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default RemoveDriverModal;
