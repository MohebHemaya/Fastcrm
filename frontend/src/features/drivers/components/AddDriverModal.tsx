import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import { UserPlusIcon, ExclamationCircleIcon } from '@/components/icons';
import { FormInput, FormSelect, FormTextarea } from '@/components/form';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import driverService from '@/services/driverService';
import type { Driver, WhatsappGroup } from '@/types/driver';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface AddDriverFormData {
  phone: string;
  name?: string;
  group_type: 'existing' | 'new';
  group_id?: string;
  group_name?: string;
  notes?: string;
}

interface AddDriverModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (newDriver: Driver) => void;
  existingGroups: WhatsappGroup[];
}

export const AddDriverModal: React.FC<AddDriverModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  existingGroups,
}) => {
  const isMobile = useIsMobile();
  const addToast = useUiStore((state) => state.addToast);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [groupMode, setGroupMode] = useState<'existing' | 'new' | 'none'>(
    existingGroups.length > 0 ? 'existing' : 'none'
  );

  const {
    control,
    handleSubmit,
    setError,
    reset,
    setValue,
  } = useForm<AddDriverFormData>({
    defaultValues: {
      phone: '',
      name: '',
      group_type: existingGroups.length > 0 ? 'existing' : 'new',
      group_id: '',
      group_name: '',
      notes: '',
    },
  });

  useEffect(() => {
    if (isOpen) {
      reset({
        phone: '',
        name: '',
        group_type: existingGroups.length > 0 ? 'existing' : 'new',
        group_id: '',
        group_name: '',
        notes: '',
      });
      setGroupMode(existingGroups.length > 0 ? 'existing' : 'none');
      setGeneralError(null);
    }
  }, [isOpen, reset, existingGroups]);

  const handleGroupModeChange = (mode: 'existing' | 'new' | 'none') => {
    setGroupMode(mode);
    if (mode === 'none') {
      setValue('group_id', '');
      setValue('group_name', '');
    } else if (mode === 'existing') {
      setValue('group_type', 'existing');
      setValue('group_name', '');
    } else if (mode === 'new') {
      setValue('group_type', 'new');
      setValue('group_id', '');
    }
  };

  const onSubmit = async (data: AddDriverFormData) => {
    setIsSubmitting(true);
    setGeneralError(null);

    const payload: any = {
      phone: data.phone.trim(),
      name: data.name?.trim() || null,
      notes: data.notes?.trim() || null,
    };

    if (groupMode === 'existing' && data.group_id) {
      payload.group_id = Number(data.group_id);
    } else if (groupMode === 'new' && data.group_name?.trim()) {
      payload.group_name = data.group_name.trim();
    }

    try {
      const response = await driverService.createDriver(payload);
      addToast({
        type: 'success',
        message: ar.drivers.driverCreated,
      });
      onSuccess(response.data);
      onClose();
    } catch (err: any) {
      const status = err?.response?.status;
      const resData = err?.response?.data;

      if (status === 422 && resData?.errors) {
        Object.entries(resData.errors).forEach(([field, msgs]: [string, any]) => {
          if (['phone', 'name', 'group_id', 'group_name', 'notes'].includes(field)) {
            setError(field as any, {
              type: 'server',
              message: msgs[0],
            });
          }
        });
        setGeneralError(resData?.message || ar.errors.generalError);
      } else {
        setGeneralError(resData?.message || ar.errors.networkError);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const groupOptions = existingGroups.map((g) => ({
    key: String(g.id),
    label: g.name,
  }));

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="center"
      size={isMobile ? 'full' : 'lg'}
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
            <ModalHeader className="flex items-center gap-2">
              <UserPlusIcon className="h-5 w-5 text-primary" />
              <span className="text-lg font-bold">{ar.drivers.addDriver}</span>
            </ModalHeader>

            <ModalBody className="py-4 flex flex-col gap-4">
              {generalError && (
                <div className="flex items-center gap-2 rounded-xl bg-danger-50 p-3 text-sm text-danger border border-danger-200">
                  <ExclamationCircleIcon className="h-4 w-4 shrink-0" />
                  <span>{generalError}</span>
                </div>
              )}

              <FormInput
                name="phone"
                control={control}
                label={ar.drivers.phone}
                placeholder="مثال: 01012345678 أو +201012345678"
                isRequired
                type="tel"
                inputDir="ltr"
                rules={{ required: ar.drivers.phoneRequired }}
                classNames={{
                  input: 'text-end font-mono',
                }}
              />

              <FormInput
                name="name"
                control={control}
                label={ar.drivers.name}
                placeholder="اسم السائق (اختياري)"
              />

              {/* Group selection mode */}
              <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between text-xs text-default-500 font-medium">
                  <span>{ar.drivers.groups}</span>
                  <div className="flex gap-2">
                    {existingGroups.length > 0 && (
                      <button
                        type="button"
                        className={`hover:underline ${groupMode === 'existing' ? 'text-primary font-bold' : ''}`}
                        onClick={() => handleGroupModeChange('existing')}
                      >
                        مجموعة سابقة
                      </button>
                    )}
                    <button
                      type="button"
                      className={`hover:underline ${groupMode === 'new' ? 'text-primary font-bold' : ''}`}
                      onClick={() => handleGroupModeChange('new')}
                    >
                      {ar.drivers.newGroup}
                    </button>
                    <button
                      type="button"
                      className={`hover:underline ${groupMode === 'none' ? 'text-primary font-bold' : ''}`}
                      onClick={() => handleGroupModeChange('none')}
                    >
                      بدون مجموعة
                    </button>
                  </div>
                </div>

                {groupMode === 'existing' && (
                  <FormSelect
                    name="group_id"
                    control={control}
                    label={ar.drivers.selectGroup}
                    placeholder={ar.drivers.selectGroup}
                    options={groupOptions}
                  />
                )}

                {groupMode === 'new' && (
                  <FormInput
                    name="group_name"
                    control={control}
                    label={ar.drivers.newGroupName}
                    placeholder="مثال: سائقين الجيزة"
                  />
                )}
              </div>

              <FormTextarea
                name="notes"
                control={control}
                label={ar.drivers.notes}
                placeholder={ar.drivers.notesPlaceholder}
                minRows={2}
              />
            </ModalBody>

            <ModalFooter className="flex flex-col-reverse sm:flex-row gap-2 w-full safe-bottom">
              <Button
                variant="flat"
                color="default"
                className="w-full sm:w-auto min-h-[44px]"
                onPress={onClose}
                isDisabled={isSubmitting}
              >
                {ar.common.cancel}
              </Button>
              <Button
                color="primary"
                type="submit"
                className="w-full sm:w-auto min-h-[44px]"
                isLoading={isSubmitting}
              >
                {ar.common.add}
              </Button>
            </ModalFooter>
          </form>
        )}
      </ModalContent>
    </Modal>
  );
};

export default AddDriverModal;
