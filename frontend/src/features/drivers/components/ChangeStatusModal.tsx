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
  Textarea,
} from '@heroui/react';
import { ExclamationCircleIcon, ArrowPathIcon, CheckCircleIcon } from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import driverService from '@/services/driverService';
import type { Driver, DriverStatusValue } from '@/types/driver';
import { ALLOWED_STATUS_TRANSITIONS } from '@/types/driver';

interface ChangeStatusModalProps {
  driver: Driver | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (updatedDriver: Driver) => void;
}

export const ChangeStatusModal: React.FC<ChangeStatusModalProps> = ({
  driver,
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [selectedStatus, setSelectedStatus] = useState<DriverStatusValue | ''>('');
  const [reason, setReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const addToast = useUiStore((state) => state.addToast);

  const currentStatus = driver?.status.value;
  const isOptedOut = currentStatus === 'opted_out';
  const allowedTransitions = currentStatus ? ALLOWED_STATUS_TRANSITIONS[currentStatus] || [] : [];

  useEffect(() => {
    if (isOpen) {
      setSelectedStatus('');
      setReason('');
      setError(null);
    }
  }, [isOpen, driver]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!driver || !selectedStatus) return;

    setIsSubmitting(true);
    setError(null);

    try {
      const response = await driverService.changeStatus(driver.id, {
        status: selectedStatus,
        reason: reason.trim() || undefined,
      });

      addToast({
        type: 'success',
        message: ar.drivers.statusChanged,
      });

      onSuccess(response.data);
      onClose();
    } catch (err: any) {
      const resData = err?.response?.data;
      setError(resData?.message || ar.errors.networkError);
    } finally {
      setIsSubmitting(false);
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
          <form onSubmit={handleSubmit}>
            <ModalHeader className="flex items-center gap-2">
              <ArrowPathIcon className="h-5 w-5 text-primary" />
              <span className="text-lg font-bold">{ar.drivers.changeStatus}</span>
            </ModalHeader>

            <ModalBody className="py-4 flex flex-col gap-4">
              {driver && (
                <div className="flex items-center justify-between p-3 rounded-xl bg-default-50 border border-default-200">
                  <div className="flex flex-col text-start">
                    <span className="text-sm font-bold text-default-900">{driver.name || 'بدون اسم'}</span>
                    <span className="text-xs text-default-400 font-mono" dir="ltr">{driver.phone}</span>
                  </div>
                  <div className="text-end">
                    <span className="text-xs text-default-500 font-medium">الحالة الحالية: </span>
                    <span className="text-xs font-bold text-default-800">{driver.status.label}</span>
                  </div>
                </div>
              )}

              {error && (
                <div className="flex items-center gap-2 rounded-xl bg-danger-50 p-3 text-sm text-danger border border-danger-200">
                  <ExclamationCircleIcon className="h-4 w-4 shrink-0" />
                  <span>{error}</span>
                </div>
              )}

              {isOptedOut ? (
                <div className="rounded-xl bg-danger-50 p-3 text-sm text-danger border border-danger-200 leading-relaxed">
                  {ar.drivers.optedOutAlert}
                </div>
              ) : allowedTransitions.length === 0 ? (
                <div className="rounded-xl bg-default-100 p-3 text-sm text-default-600">
                  لا توجد تحويلات حالة متاحة لهذا السائق.
                </div>
              ) : (
                <>
                  <Select
                    label={ar.drivers.changeStatus}
                    placeholder="اختر الحالة الجديدة"
                    variant="bordered"
                    labelPlacement="outside"
                    selectedKeys={selectedStatus ? [selectedStatus] : []}
                    onChange={(e) => setSelectedStatus(e.target.value as DriverStatusValue)}
                    isRequired
                  >
                    {allowedTransitions.map((target) => (
                      <SelectItem key={target} textValue={ar.statuses[target] || target}>
                        {ar.statuses[target] || target}
                      </SelectItem>
                    ))}
                  </Select>

                  <Textarea
                    label={ar.drivers.statusReason}
                    placeholder={ar.drivers.statusReasonPlaceholder}
                    variant="bordered"
                    labelPlacement="outside"
                    value={reason}
                    onValueChange={setReason}
                    minRows={2}
                  />
                </>
              )}
            </ModalBody>

            <ModalFooter className="flex justify-end gap-2">
              <Button variant="flat" color="default" onPress={onClose} isDisabled={isSubmitting}>
                {ar.common.cancel}
              </Button>
              {!isOptedOut && allowedTransitions.length > 0 && (
                <Button
                  color="primary"
                  type="submit"
                  isLoading={isSubmitting}
                  isDisabled={!selectedStatus}
                  startContent={!isSubmitting && <CheckCircleIcon className="h-4 w-4" />}
                >
                  {ar.common.save}
                </Button>
              )}
            </ModalFooter>
          </form>
        )}
      </ModalContent>
    </Modal>
  );
};

export default ChangeStatusModal;
