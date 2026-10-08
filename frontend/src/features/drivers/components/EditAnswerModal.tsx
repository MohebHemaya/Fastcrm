import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Select,
  SelectItem,
} from '@heroui/react';
import { PencilSquareIcon, CheckIcon } from '@/components/icons';
import type { DriverAnswerItem } from '@/types/question';

interface EditAnswerModalProps {
  isOpen: boolean;
  onClose: () => void;
  answer: DriverAnswerItem | null;
  onSave: (payload: { value_text?: string; value_number?: number; option_id?: number }) => Promise<void>;
}

export const EditAnswerModal: React.FC<EditAnswerModalProps> = ({
  isOpen,
  onClose,
  answer,
  onSave,
}) => {
  const [valueText, setValueText] = useState('');
  const [valueNumber, setValueNumber] = useState<string>('');
  const [selectedOptionId, setSelectedOptionId] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (answer) {
      setValueText(answer.value_text || '');
      setValueNumber(answer.value_number !== null ? String(answer.value_number) : '');
      setSelectedOptionId(answer.option_id ? String(answer.option_id) : '');
    }
  }, [answer, isOpen]);

  if (!answer) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      if (answer.type === 'single_choice') {
        await onSave({
          option_id: selectedOptionId ? Number(selectedOptionId) : undefined,
        });
      } else if (answer.type === 'yes_no') {
        await onSave({
          value_text: valueText,
        });
      } else if (answer.type === 'number') {
        await onSave({
          value_number: valueNumber ? Number(valueNumber) : undefined,
          value_text: valueNumber,
        });
      } else {
        await onSave({
          value_text: valueText,
        });
      }
      onClose();
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="md"
      classNames={{
        base: 'border border-default-200 bg-content1 text-foreground rtl',
        header: 'border-b border-default-100',
        footer: 'border-t border-default-100',
      }}
    >
      <ModalContent>
        <form onSubmit={handleSubmit}>
          <ModalHeader className="flex items-center gap-2 border-b border-default-100 text-start">
            <PencilSquareIcon className="w-5 h-5 text-primary" />
            <span>تعديل إجابة السؤال (تدقيق يدوي)</span>
          </ModalHeader>

          <ModalBody className="py-4 space-y-4 text-start">
            <div className="p-3 rounded-xl bg-default-100/60 border border-default-200">
              <p className="text-xs text-default-500 font-medium">السؤال (الإصدار v{answer.version}):</p>
              <p className="text-sm font-bold text-foreground mt-0.5">{answer.question_text}</p>
            </div>

            {answer.type === 'single_choice' && (
              <div>
                <Select
                  label="اختر الإجابة المعدلة"
                  variant="bordered"
                  labelPlacement="outside"
                  selectedKeys={selectedOptionId ? [selectedOptionId] : []}
                  onChange={(e) => setSelectedOptionId(e.target.value)}
                  isRequired
                >
                  {(answer.available_options || []).map((opt) => (
                    <SelectItem key={String(opt.id)}>{opt.label_ar}</SelectItem>
                  ))}
                </Select>
              </div>
            )}

            {answer.type === 'yes_no' && (
              <div>
                <Select
                  label="اختر نعم أو لا"
                  variant="bordered"
                  labelPlacement="outside"
                  selectedKeys={valueText ? [valueText] : []}
                  onChange={(e) => setValueText(e.target.value)}
                  isRequired
                >
                  <SelectItem key="نعم">نعم</SelectItem>
                  <SelectItem key="لا">لا</SelectItem>
                </Select>
              </div>
            )}

            {answer.type === 'number' && (
              <div>
                <Input
                  type="number"
                  variant="bordered"
                  labelPlacement="outside"
                  label="القيمة الرقمية المعدلة"
                  value={valueNumber}
                  onValueChange={setValueNumber}
                  dir="ltr"
                  classNames={{
                    input: 'text-start',
                  }}
                  isRequired
                />
              </div>
            )}

            {answer.type === 'text' && (
              <div>
                <Input
                  label="النص المعدل"
                  variant="bordered"
                  labelPlacement="outside"
                  value={valueText}
                  onValueChange={setValueText}
                  isRequired
                />
              </div>
            )}
          </ModalBody>

          <ModalFooter className="border-t border-default-100 flex justify-end gap-2">
            <Button variant="flat" onPress={onClose} isDisabled={isSubmitting}>
              إلغاء
            </Button>
            <Button
              type="submit"
              color="primary"
              isLoading={isSubmitting}
              startContent={!isSubmitting && <CheckIcon className="w-4 h-4" />}
            >
              حفظ التعديل
            </Button>
          </ModalFooter>
        </form>
      </ModalContent>
    </Modal>
  );
};

export default EditAnswerModal;
