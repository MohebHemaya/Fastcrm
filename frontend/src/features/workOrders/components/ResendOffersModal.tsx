import React from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import ar from '@/i18n/ar';

interface ResendOffersModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => Promise<void>;
  isLoading?: boolean;
}

export const ResendOffersModal: React.FC<ResendOffersModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  isLoading = false,
}) => {
  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md" placement="center">
      <ModalContent>
        {(closeModal) => (
          <>
            <ModalHeader className="flex flex-col gap-1 text-start">
              {ar.workOrders.resendModal.title}
            </ModalHeader>
            <ModalBody className="text-start space-y-3">
              <p className="text-default-700 leading-relaxed">
                {ar.workOrders.resendModal.description}
              </p>
              <div className="p-3 rounded-xl bg-warning-50 border border-warning-200 text-xs text-warning-800">
                ملاحظة: ستعود حالة أمر العمل إلى "جاري استقبال السائقين" ويتم قبول أول السائقين ردًا حتى اكتمال العدد.
              </div>
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
                color="warning"
                variant="solid"
                onPress={onConfirm}
                onClick={onConfirm}
                isLoading={isLoading}
              >
                {ar.workOrders.resendModal.confirm}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default ResendOffersModal;
