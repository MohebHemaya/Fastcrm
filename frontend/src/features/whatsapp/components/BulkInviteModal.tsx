import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Spinner,
  RadioGroup,
  Radio,
} from '@heroui/react';
import {
  PaperAirplaneIcon,
  UsersIcon,
  ClockIcon,
  ExclamationCircleIcon,
  ShieldCheckIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type { InviteEstimate } from '@/types/whatsapp';

interface BulkInviteModalProps {
  selectedDriverIds?: number[];
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export const BulkInviteModal: React.FC<BulkInviteModalProps> = ({
  selectedDriverIds = [],
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [inviteScope, setInviteScope] = useState<'selected' | 'all_new'>(
    selectedDriverIds.length > 0 ? 'selected' : 'all_new'
  );
  const [estimate, setEstimate] = useState<InviteEstimate | null>(null);
  const [isLoadingEstimate, setIsLoadingEstimate] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  const fetchEstimate = async (scope: 'selected' | 'all_new') => {
    setIsLoadingEstimate(true);
    try {
      const res = await whatsappService.estimateInvites({
        driver_ids: scope === 'selected' ? selectedDriverIds : undefined,
        all_new: scope === 'all_new',
      });
      setEstimate(res.data);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsLoadingEstimate(false);
    }
  };

  const selectedIdsKey = selectedDriverIds.join(',');

  useEffect(() => {
    if (isOpen) {
      const initialScope = selectedDriverIds.length > 0 ? 'selected' : 'all_new';
      setInviteScope(initialScope);
      fetchEstimate(initialScope);
    } else {
      setEstimate(null);
    }
  }, [isOpen, selectedIdsKey]);

  const handleScopeChange = (value: string) => {
    const newScope = value as 'selected' | 'all_new';
    setInviteScope(newScope);
    fetchEstimate(newScope);
  };

  const handleConfirm = async () => {
    setIsSubmitting(true);
    try {
      const res = await whatsappService.inviteDrivers({
        driver_ids: inviteScope === 'selected' ? selectedDriverIds : undefined,
        all_new: inviteScope === 'all_new',
      });
      addToast({
        title: ar.whatsapp.inviteSuccess,
        message: res.message || ar.whatsapp.inviteSuccess,
        type: 'success',
      });
      onSuccess?.();
      onClose();
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2 border-b border-default-200">
          <PaperAirplaneIcon className="w-5 h-5 text-default-600 rtl:-scale-x-100 shrink-0" />
          <span className="text-base font-bold text-default-900">
            {ar.whatsapp.inviteConfirmTitle}
          </span>
        </ModalHeader>

        <ModalBody className="py-4 space-y-4">
          <p className="text-sm text-default-600 leading-relaxed">
            {ar.whatsapp.inviteConfirmMsg}
          </p>

          {/* Option to choose between selected and all new */}
          {selectedDriverIds.length > 0 && (
            <RadioGroup
              value={inviteScope}
              onValueChange={handleScopeChange}
              label={ar.common.actions}
              classNames={{ label: 'text-xs font-semibold text-default-700 mb-1' }}
            >
              <Radio value="selected">
                {ar.whatsapp.inviteSelected.replace('{count}', selectedDriverIds.length.toString())}
              </Radio>
              <Radio value="all_new">{ar.whatsapp.inviteAllNew}</Radio>
            </RadioGroup>
          )}

          {/* Estimation Summary Box */}
          <div className="rounded-xl bg-default-100/70 border border-default-200 p-4 space-y-3">
            {isLoadingEstimate ? (
              <div className="py-4 flex justify-center items-center">
                <Spinner size="sm" color="success" label={ar.app.loading} />
              </div>
            ) : estimate ? (
              <>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-default-600 flex items-center gap-2">
                    <UsersIcon className="w-4 h-4 text-primary-500" />
                    {ar.whatsapp.eligibleCount}:
                  </span>
                  <span className="font-bold text-default-900 font-mono text-base">
                    {estimate.count}
                  </span>
                </div>

                <div className="flex items-center justify-between text-sm">
                  <span className="text-default-600 flex items-center gap-2">
                    <ClockIcon className="w-4 h-4 text-warning-500" />
                    {ar.whatsapp.estimatedDuration}:
                  </span>
                  <span className="font-semibold text-default-900">
                    {estimate.estimated_minutes} {ar.whatsapp.minutes}
                  </span>
                </div>

                <div className="pt-2 border-t border-default-200/60 text-xs text-default-500">
                  {ar.whatsapp.sendRateNotice.replace('{rate}', estimate.send_rate_per_min.toString())}
                </div>

                {estimate.anti_ban_enabled && (
                  <div className="flex items-center gap-2 p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs leading-relaxed">
                    <ShieldCheckIcon className="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <div>
                      <span className="font-semibold block">حماية الخط من الحظر نشطة</span>
                      <span className="text-[11px] opacity-90">
                        فواصل إرسال عشوائية ({estimate.anti_ban_min_delay} - {estimate.anti_ban_max_delay} ث) مع فترات راحة ذكية لضمان سلامة الرقم.
                      </span>
                    </div>
                  </div>
                )}
              </>
            ) : null}
          </div>

          {estimate && estimate.count === 0 && (
            <div className="p-3 bg-warning-50 border border-warning-200 text-warning-700 rounded-lg text-xs flex items-center gap-2">
              <ExclamationCircleIcon className="w-4 h-4 shrink-0" />
              <span>{ar.whatsapp.noEligibleDrivers}</span>
            </div>
          )}
        </ModalBody>

        <ModalFooter className="border-t border-default-200 py-3">
          <Button variant="light" size="sm" onClick={onClose} disabled={isSubmitting}>
            {ar.common.cancel}
          </Button>
          <Button
            color="success"
            size="sm"
            startContent={<PaperAirplaneIcon className="w-4 h-4 rtl:-scale-x-100" />}
            isLoading={isSubmitting}
            disabled={isLoadingEstimate || !estimate || estimate.count === 0}
            onClick={handleConfirm}
          >
            {ar.whatsapp.confirmSend}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default BulkInviteModal;
