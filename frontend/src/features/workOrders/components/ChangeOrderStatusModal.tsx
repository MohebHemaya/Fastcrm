import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  RadioGroup,
  Radio,
  Textarea,
} from '@heroui/react';
import type { WorkOrderStatus } from '@/types/workOrder';
import ar from '@/i18n/ar';

interface ChangeOrderStatusModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentStatus: WorkOrderStatus;
  onConfirm: (
    newStatus: 'in_progress' | 'completed' | 'cancelled',
    reason?: string
  ) => Promise<void>;
  isLoading?: boolean;
}

export const ChangeOrderStatusModal: React.FC<ChangeOrderStatusModalProps> = ({
  isOpen,
  onClose,
  currentStatus,
  onConfirm,
  isLoading = false,
}) => {
  const [selectedStatus, setSelectedStatus] = useState<
    'in_progress' | 'completed' | 'cancelled'
  >('in_progress');
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState('');

  useEffect(() => {
    if (isOpen) {
      setReason('');
      setReasonError('');
      if (currentStatus === 'filled') {
        setSelectedStatus('in_progress');
      } else if (currentStatus === 'in_progress') {
        setSelectedStatus('completed');
      } else {
        setSelectedStatus('cancelled');
      }
    }
  }, [isOpen, currentStatus]);

  const handleConfirm = async () => {
    if (selectedStatus === 'cancelled' && !reason.trim()) {
      setReasonError(ar.workOrders.statusModal.reasonLabel);
      return;
    }
    setReasonError('');
    await onConfirm(selectedStatus, reason.trim() || undefined);
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md" placement="center">
      <ModalContent>
        {(closeModal) => (
          <>
            <ModalHeader className="flex flex-col gap-1 text-start">
              {ar.workOrders.statusModal.title}
            </ModalHeader>
            <ModalBody className="text-start space-y-4">
              <RadioGroup
                label={ar.workOrders.statusModal.selectStatus}
                value={selectedStatus}
                onValueChange={(val) =>
                  setSelectedStatus(val as 'in_progress' | 'completed' | 'cancelled')
                }
              >
                {currentStatus === 'filled' && (
                  <Radio value="in_progress">
                    {ar.workOrders.statusModal.inProgress}
                  </Radio>
                )}
                {currentStatus === 'in_progress' && (
                  <Radio value="completed">
                    {ar.workOrders.statusModal.completed}
                  </Radio>
                )}
                <Radio value="cancelled" className="text-danger">
                  <span className="text-danger font-medium">
                    {ar.workOrders.statusModal.cancelled}
                  </span>
                </Radio>
              </RadioGroup>

              {selectedStatus === 'cancelled' && (
                <Textarea
                  label={ar.workOrders.statusModal.reasonLabel}
                  placeholder={ar.workOrders.statusModal.reasonPlaceholder}
                  variant="bordered"
                  labelPlacement="outside"
                  value={reason}
                  onValueChange={(val) => {
                    setReason(val);
                    if (reasonError) setReasonError('');
                  }}
                  isInvalid={Boolean(reasonError)}
                  errorMessage={reasonError}
                  isRequired
                  minRows={3}
                />
              )}
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
                color={selectedStatus === 'cancelled' ? 'danger' : 'primary'}
                onPress={handleConfirm}
                onClick={handleConfirm}
                isLoading={isLoading}
              >
                {ar.workOrders.statusModal.confirm}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default ChangeOrderStatusModal;
