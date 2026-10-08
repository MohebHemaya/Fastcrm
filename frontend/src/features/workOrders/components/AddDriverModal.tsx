import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Select,
  SelectItem,
  RadioGroup,
  Radio,
} from '@heroui/react';
import { driverService } from '@/services/driverService';
import type { Driver } from '@/types/driver';
import ar from '@/i18n/ar';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface AddDriverModalProps {
  isOpen: boolean;
  onClose: () => void;
  canAddConfirmed: boolean;
  canAddBackup: boolean;
  onConfirm: (driverId: number, as: 'confirmed' | 'backup') => Promise<void>;
  isLoading?: boolean;
}

export const AddDriverModal: React.FC<AddDriverModalProps> = ({
  isOpen,
  onClose,
  canAddConfirmed,
  canAddBackup,
  onConfirm,
  isLoading = false,
}) => {
  const isMobile = useIsMobile();
  const [drivers, setDrivers] = useState<Driver[]>([]);
  const [fetchingDrivers, setFetchingDrivers] = useState(false);
  const [selectedDriverId, setSelectedDriverId] = useState<string>('');
  const [role, setRole] = useState<'confirmed' | 'backup'>('confirmed');
  const [error, setError] = useState<string>('');

  useEffect(() => {
    if (isOpen) {
      setSelectedDriverId('');
      setError('');
      setRole(canAddConfirmed ? 'confirmed' : 'backup');

      // Fetch active drivers
      setFetchingDrivers(true);
      driverService
        .getDrivers({ status: 'active', per_page: 50 })
        .then((res) => {
          setDrivers(res.data || []);
        })
        .catch(() => {
          setDrivers([]);
        })
        .finally(() => {
          setFetchingDrivers(false);
        });
    }
  }, [isOpen, canAddConfirmed]);

  const handleConfirm = async () => {
    if (!selectedDriverId) {
      setError(ar.workOrders.addDriverModal.selectDriverPlaceholder);
      return;
    }
    setError('');
    await onConfirm(parseInt(selectedDriverId, 10), role);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : 'md'}
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
              {ar.workOrders.addDriverModal.title}
            </ModalHeader>
            <ModalBody className="text-start space-y-4">
              <Select
                label={ar.workOrders.addDriverModal.selectDriver}
                placeholder={ar.workOrders.addDriverModal.selectDriverPlaceholder}
                variant="bordered"
                labelPlacement="outside"
                selectedKeys={selectedDriverId ? [selectedDriverId] : []}
                onChange={(e) => {
                  setSelectedDriverId(e.target.value);
                  if (error) setError('');
                }}
                isLoading={fetchingDrivers}
                isInvalid={Boolean(error)}
                errorMessage={error}
                isRequired
              >
                {drivers.map((d) => (
                  <SelectItem key={d.id} textValue={`${d.name || 'كابتن'} (${d.phone})`}>
                    <div className="flex justify-between items-center w-full">
                      <span className="font-medium text-default-900">
                        {d.name || 'كابتن'}
                      </span>
                      <span className="text-xs text-default-500 font-mono">
                        {d.phone}
                      </span>
                    </div>
                  </SelectItem>
                ))}
              </Select>

              <RadioGroup
                label={ar.workOrders.addDriverModal.role}
                value={role}
                onValueChange={(val) => setRole(val as 'confirmed' | 'backup')}
              >
                <Radio
                  value="confirmed"
                  isDisabled={!canAddConfirmed}
                  description={!canAddConfirmed ? 'اكتملت المقاعد المؤكدة' : undefined}
                >
                  {ar.workOrders.addDriverModal.confirmedRole}
                </Radio>
                <Radio
                  value="backup"
                  isDisabled={!canAddBackup}
                  description={!canAddBackup ? 'اكتملت المقاعد الاحتياطية' : undefined}
                >
                  {ar.workOrders.addDriverModal.backupRole}
                </Radio>
              </RadioGroup>
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
                disabled={!selectedDriverId}
              >
                {ar.workOrders.addDriverModal.confirm}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default AddDriverModal;
