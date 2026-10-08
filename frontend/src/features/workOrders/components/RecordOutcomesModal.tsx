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
  Input,
  Card,
  CardBody,
} from '@heroui/react';
import type { WorkOrderRecipient, RecordOutcomeItem } from '@/types/workOrder';
import ar from '@/i18n/ar';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface RecordOutcomesModalProps {
  isOpen: boolean;
  onClose: () => void;
  confirmedRecipients: WorkOrderRecipient[];
  onConfirm: (outcomes: RecordOutcomeItem[]) => Promise<void>;
  isLoading?: boolean;
}

export const RecordOutcomesModal: React.FC<RecordOutcomesModalProps> = ({
  isOpen,
  onClose,
  confirmedRecipients,
  onConfirm,
  isLoading = false,
}) => {
  const isMobile = useIsMobile();
  const [formState, setFormState] = useState<
    Record<
      number,
      {
        outcome: 'attended' | 'late' | 'no_show' | 'cancelled_late';
        rating: string;
        notes: string;
      }
    >
  >({});

  useEffect(() => {
    if (isOpen) {
      const initial: Record<
        number,
        {
          outcome: 'attended' | 'late' | 'no_show' | 'cancelled_late';
          rating: string;
          notes: string;
        }
      > = {};

      confirmedRecipients.forEach((r) => {
        initial[r.id] = {
          outcome: r.outcome || 'attended',
          rating: r.rating ? String(r.rating) : '',
          notes: r.outcome_notes || '',
        };
      });

      setFormState(initial);
    }
  }, [isOpen, confirmedRecipients]);

  const handleOutcomeChange = (
    recipientId: number,
    outcome: 'attended' | 'late' | 'no_show' | 'cancelled_late'
  ) => {
    setFormState((prev) => ({
      ...prev,
      [recipientId]: {
        ...prev[recipientId],
        outcome,
        // If no_show or cancelled_late, clear rating
        rating: outcome === 'no_show' || outcome === 'cancelled_late' ? '' : prev[recipientId]?.rating || '',
      },
    }));
  };

  const handleRatingChange = (recipientId: number, rating: string) => {
    setFormState((prev) => ({
      ...prev,
      [recipientId]: {
        ...prev[recipientId],
        rating,
      },
    }));
  };

  const handleNotesChange = (recipientId: number, notes: string) => {
    setFormState((prev) => ({
      ...prev,
      [recipientId]: {
        ...prev[recipientId],
        notes,
      },
    }));
  };

  const handleSubmit = async () => {
    const items: RecordOutcomeItem[] = confirmedRecipients.map((r) => {
      const st = formState[r.id] || {
        outcome: 'attended',
        rating: '',
        notes: '',
      };
      return {
        recipient_id: r.id,
        outcome: st.outcome,
        rating: st.rating ? parseInt(st.rating, 10) : null,
        notes: st.notes.trim() || null,
      };
    });

    await onConfirm(items);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : '2xl'}
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
              {ar.workOrders.outcomes.title}
              <p className="text-xs font-normal text-default-500">
                {ar.workOrders.outcomes.description}
              </p>
            </ModalHeader>
            <ModalBody className="text-start space-y-4">
              {confirmedRecipients.length === 0 ? (
                <p className="text-center text-default-500 py-6">
                  لا يوجد سائقون مؤكدون في هذا الطلب لتسجيل النتائج.
                </p>
              ) : (
                confirmedRecipients.map((recipient) => {
                  const state = formState[recipient.id] || {
                    outcome: 'attended',
                    rating: '',
                    notes: '',
                  };
                  const disableRating =
                    state.outcome === 'no_show' || state.outcome === 'cancelled_late';

                  return (
                    <Card key={recipient.id} className="border border-default-200 shadow-none">
                      <CardBody className="p-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <div>
                            <span className="font-semibold text-foreground text-sm">
                              {recipient.driver_name}
                            </span>
                            <span dir="ltr" className="text-xs text-default-500 font-mono ms-2">
                              {recipient.driver_phone}
                            </span>
                          </div>
                          {recipient.sequence_number && (
                            <span className="text-xs font-mono font-bold text-primary bg-primary-50 px-2 py-0.5 rounded">
                              #{recipient.sequence_number}
                            </span>
                          )}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                          {/* Outcome select */}
                          <Select
                            label={ar.workOrders.outcomes.outcome}
                            size="sm"
                            variant="bordered"
                            labelPlacement="outside"
                            selectedKeys={[state.outcome]}
                            onChange={(e) =>
                              handleOutcomeChange(
                                recipient.id,
                                e.target.value as 'attended' | 'late' | 'no_show' | 'cancelled_late'
                              )
                            }
                            disallowEmptySelection
                          >
                            <SelectItem key="attended" textValue={ar.workOrders.outcomes.attended}>
                              {ar.workOrders.outcomes.attended}
                            </SelectItem>
                            <SelectItem key="late" textValue={ar.workOrders.outcomes.late}>
                              {ar.workOrders.outcomes.late}
                            </SelectItem>
                            <SelectItem key="no_show" textValue={ar.workOrders.outcomes.noShow}>
                              {ar.workOrders.outcomes.noShow}
                            </SelectItem>
                            <SelectItem
                              key="cancelled_late"
                              textValue={ar.workOrders.outcomes.cancelledLate}
                            >
                              {ar.workOrders.outcomes.cancelledLate}
                            </SelectItem>
                          </Select>

                          {/* Rating select */}
                          <Select
                            label={ar.workOrders.outcomes.rating}
                            size="sm"
                            variant="bordered"
                            labelPlacement="outside"
                            selectedKeys={state.rating ? [state.rating] : []}
                            onChange={(e) => handleRatingChange(recipient.id, e.target.value)}
                            isDisabled={disableRating}
                            placeholder="بدون تقييم"
                          >
                            <SelectItem key="5" textValue="5 - ممتاز ⭐⭐⭐⭐⭐">
                              5 - ممتاز ⭐⭐⭐⭐⭐
                            </SelectItem>
                            <SelectItem key="4" textValue="4 - جيد جداً ⭐⭐⭐⭐">
                              4 - جيد جداً ⭐⭐⭐⭐
                            </SelectItem>
                            <SelectItem key="3" textValue="3 - جيد ⭐⭐⭐">
                              3 - جيد ⭐⭐⭐
                            </SelectItem>
                            <SelectItem key="2" textValue="2 - مقبول ⭐⭐">
                              2 - مقبول ⭐⭐
                            </SelectItem>
                            <SelectItem key="1" textValue="1 - سيء ⭐">
                              1 - سيء ⭐
                            </SelectItem>
                          </Select>

                          {/* Notes input */}
                          <Input
                            label={ar.workOrders.outcomes.notes}
                            size="sm"
                            variant="bordered"
                            labelPlacement="outside"
                            value={state.notes}
                            onValueChange={(val) => handleNotesChange(recipient.id, val)}
                            placeholder="ملاحظات..."
                          />
                        </div>
                      </CardBody>
                    </Card>
                  );
                })
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
                onPress={handleSubmit}
                onClick={handleSubmit}
                isLoading={isLoading}
                isDisabled={confirmedRecipients.length === 0}
              >
                {ar.workOrders.outcomes.save}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
};

export default RecordOutcomesModal;
