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
} from '@heroui/react';
import type { WorkOrderRecipient } from '@/types/workOrder';
import ar from '@/i18n/ar';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface PromoteBackupModalProps {
  isOpen: boolean;
  onClose: () => void;
  backups: WorkOrderRecipient[];
  onConfirm: (recipientId?: number) => Promise<void>;
  isLoading?: boolean;
}

export const PromoteBackupModal: React.FC<PromoteBackupModalProps> = ({
  isOpen,
  onClose,
  backups,
  onConfirm,
  isLoading = false,
}) => {
  const isMobile = useIsMobile();
  const [selectedId, setSelectedId] = useState<string>('');

  useEffect(() => {
    if (backups.length > 0) {
      // Default to the first backup (lowest sequence number)
      setSelectedId(String(backups[0].id));
    } else {
      setSelectedId('');
    }
  }, [backups, isOpen]);

  const handleConfirm = async () => {
    const rId = selectedId ? parseInt(selectedId, 10) : undefined;
    await onConfirm(rId);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : 'lg'}
      scrollBehavior="inside"
      placement="center"
      classNames={{
        base: isMobile ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none' : '',
      }}
    >
      <ModalContent>
        {(closeModal) => (
          <>
            <ModalHeader className="flex flex-col gap-1 text-start">
              {ar.workOrders.promoteModal.title}
            </ModalHeader>
            <ModalBody className="text-start">
              {backups.length === 0 ? (
                 <p className="text-default-500 py-4 text-center">
                  {ar.workOrders.promoteModal.empty}
                </p>
              ) : (
                <div className="space-y-4">
                  <p className="text-sm text-default-600">
                    {ar.workOrders.promoteModal.lowestNotice}
                  </p>

                  <RadioGroup
                    label={ar.workOrders.promoteModal.selectBackup}
                    value={selectedId}
                    onValueChange={setSelectedId}
                  >
                    {backups.map((b) => (
                      <Radio
                        key={b.id}
                        value={String(b.id)}
                        description={`هاتف: ${b.driver_phone}`}
                        classNames={{
                          base: 'border border-default-200 hover:border-primary p-3 rounded-lg w-full mb-2',
                        }}
                      >
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-primary">
                            احتياطي #{b.sequence_number ?? '-'}
                          </span>
                          <span className="font-medium text-default-900">
                            {b.driver_name}
                          </span>
                        </div>
                      </Radio>
                    ))}
                  </RadioGroup>
                </div>
              )}
            </ModalBody>
            <ModalFooter className="flex flex-col-reverse sm:flex-row gap-2 w-full safe-bottom">
              <Button
                variant="flat"
                color="default"
                className="w-full sm:w-auto min-h-[44px]"
                onPress={closeModal}
                onClick={closeModal}
                disabled={isLoading}
              >
                {ar.common.cancel}
              </Button>
              <Button
                color="primary"
                className="w-full sm:w-auto min-h-[44px]"
                onPress={handleConfirm}
                onClick={handleConfirm}
                isLoading={isLoading}
                disabled={backups.length === 0}
              >
                {ar.workOrders.promoteModal.confirm}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default PromoteBackupModal;
