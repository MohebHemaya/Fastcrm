import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
} from '@heroui/react';
import {
  PlusIcon,
  TrashIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  QuestionMarkCircleIcon,
  CheckIcon,
  ExclamationCircleIcon,
} from '@/components/icons';
import type { Question, QuestionType, QuestionOption } from '@/types/question';
import QuestionPreview from './QuestionPreview';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface QuestionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: {
    key: string;
    type: QuestionType;
    text_ar: string;
    is_required: boolean;
    is_filter: boolean;
    is_active: boolean;
    options?: Array<{ value: string; label_ar: string; sort_order?: number }>;
  }) => Promise<void>;
  question?: Question | null;
}

const QUESTION_TYPES: Array<{ key: QuestionType; label: string }> = [
  { key: 'single_choice', label: 'اختيار من متعدد (أزرار / قائمة)' },
  { key: 'yes_no', label: 'نعم / لا (زرين)' },
  { key: 'number', label: 'رقم (مثل سنوات الخبرة أو سعة الحمولة)' },
  { key: 'text', label: 'نص حر (إجابة نصية حرة)' },
];

export const QuestionModal: React.FC<QuestionModalProps> = ({
  isOpen,
  onClose,
  onSubmit,
  question,
}) => {
  const isMobile = useIsMobile();
  const [key, setKey] = useState('');
  const [type, setType] = useState<QuestionType>('single_choice');
  const [textAr, setTextAr] = useState('');
  const [isRequired, setIsRequired] = useState(true);
  const [isFilter, setIsFilter] = useState(false);
  const [isActive, setIsActive] = useState(true);
  const [options, setOptions] = useState<QuestionOption[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (question) {
      setKey(question.key);
      setType(question.type);
      setTextAr(question.text_ar || '');
      setIsRequired(question.is_required);
      setIsFilter(question.is_filter);
      setIsActive(question.is_active);
      setOptions(
        question.options && question.options.length > 0
          ? question.options.map((o) => ({ ...o }))
          : [
              { value: 'opt_1', label_ar: '' },
              { value: 'opt_2', label_ar: '' },
            ]
      );
    } else {
      setKey('');
      setType('single_choice');
      setTextAr('');
      setIsRequired(true);
      setIsFilter(false);
      setIsActive(true);
      setOptions([
        { value: 'opt_1', label_ar: '' },
        { value: 'opt_2', label_ar: '' },
      ]);
    }
    setErrors({});
  }, [question, isOpen]);

  const handleAddOption = () => {
    if (options.length >= 10) return;
    const newIdx = options.length + 1;
    setOptions([...options, { value: `opt_${newIdx}`, label_ar: '' }]);
  };

  const handleRemoveOption = (index: number) => {
    if (options.length <= 2) return;
    setOptions(options.filter((_, idx) => idx !== index));
  };

  const handleMoveOption = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= options.length) return;
    const newOptions = [...options];
    const temp = newOptions[index];
    newOptions[index] = newOptions[targetIdx];
    newOptions[targetIdx] = temp;
    setOptions(newOptions);
  };

  const handleOptionChange = (index: number, field: 'value' | 'label_ar', val: string) => {
    const newOptions = [...options];
    newOptions[index] = { ...newOptions[index], [field]: val };
    setOptions(newOptions);
  };

  const maxChars = options.length <= 3 ? 20 : 24;

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!key.trim()) {
      newErrors.key = 'رمز السؤال (المفتاح) مطلوب.';
    } else if (!/^[a-z0-9_]+$/.test(key.trim())) {
      newErrors.key = 'الرمز يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطة سفلية فقط.';
    }

    if (!textAr.trim()) {
      newErrors.textAr = 'نص السؤال بالعربية مطلوب.';
    }

    if (type === 'single_choice') {
      if (options.length < 2) {
        newErrors.options = 'يجب إضافة خيارين على الأقل.';
      } else if (options.length > 10) {
        newErrors.options = 'الحد الأقصى هو 10 خيارات.';
      }

      options.forEach((opt, idx) => {
        if (!opt.label_ar.trim()) {
          newErrors[`opt_label_${idx}`] = 'نص الخيار مطلوب.';
        } else if (opt.label_ar.length > maxChars) {
          newErrors[`opt_label_${idx}`] = `الحد الأقصى هو ${maxChars} حرفاً.`;
        }
        if (!opt.value.trim()) {
          newErrors[`opt_val_${idx}`] = 'رمز القيمة مطلوب.';
        }
      });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setIsSubmitting(true);
    try {
      await onSubmit({
        key: key.trim(),
        type,
        text_ar: textAr.trim(),
        is_required: isRequired,
        is_filter: isFilter,
        is_active: isActive,
        options:
          type === 'single_choice'
            ? options.map((opt, idx) => ({
                value: opt.value.trim(),
                label_ar: opt.label_ar.trim(),
                sort_order: idx + 1,
              }))
            : undefined,
      });
      onClose();
    } catch (err: any) {
      if (err.response?.data?.errors) {
        const serverErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([k, v]: [string, any]) => {
          serverErrors[k] = Array.isArray(v) ? v[0] : String(v);
        });
        setErrors(serverErrors);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : '4xl'}
      scrollBehavior="inside"
      classNames={{
        base: isMobile ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none' : 'max-h-[92vh]',
      }}
    >
      <ModalContent>
        <form onSubmit={handleSubmit}>
          <ModalHeader className="flex items-center gap-2 border-b border-divider text-start">
            <QuestionMarkCircleIcon className="w-5 h-5 text-primary" />
            <span>{question ? 'تعديل السؤال وإصدار نسخة جديدة' : 'إضافة سؤال جديد للاستبيان'}</span>
          </ModalHeader>

          <ModalBody className="py-4">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
              {/* Left Column: Form Fields */}
              <div className="lg:col-span-7 space-y-4 text-start">
                {/* Key (Slug) */}
                <div>
                  <Input
                    label="رمز السؤال الفريد (Key)"
                    labelPlacement="outside"
                    placeholder="مثال: vehicle_type, years_exp"
                    value={key}
                    onValueChange={setKey}
                    isRequired
                    variant="bordered"
                    isInvalid={!!errors.key}
                    errorMessage={errors.key}
                    description="يستخدم برمجياً وفي الفلاتر (أحرف إنجليزية وشرطة سفلية فقط)"
                  />
                </div>

                {/* Type Selection */}
                <div>
                  <Select
                    label="نوع السؤال"
                    labelPlacement="outside"
                    selectedKeys={[type]}
                    onChange={(e) => setType(e.target.value as QuestionType)}
                    isRequired
                    variant="bordered"
                    description="يحدد طريقة إرسال السؤال عبر واتساب (أزرار أو قائمة أو نص)"
                  >
                    {QUESTION_TYPES.map((t) => (
                      <SelectItem key={t.key}>{t.label}</SelectItem>
                    ))}
                  </Select>
                </div>

                {/* Question Arabic Text */}
                <div>
                  <Textarea
                    label="نص السؤال (بالعربية)"
                    labelPlacement="outside"
                    placeholder="اكتب السؤال بوضوح كما سيصل للسائق على واتساب..."
                    value={textAr}
                    onValueChange={setTextAr}
                    isRequired
                    variant="bordered"
                    minRows={3}
                    isInvalid={!!errors.textAr}
                    errorMessage={errors.textAr}
                  />
                </div>

                {/* Single Choice Options Editor */}
                {type === 'single_choice' && (
                  <div className="border border-divider rounded-xl p-3.5 bg-content2/40 space-y-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <h4 className="text-sm font-semibold">خيارات الإجابة</h4>
                        <p className="text-xs text-default-500">
                          {options.length <= 3 ? (
                            <span className="text-primary font-medium">
                              ستظهر كـ <b>أزرار واتساب سريعة</b> (الحد الأقصى {maxChars} حرفاً للزر)
                            </span>
                          ) : (
                            <span className="text-warning font-medium">
                              ستظهر كـ <b>قائمة منسدلة</b> (الحد الأقصى {maxChars} حرفاً للخيار)
                            </span>
                          )}
                        </p>
                      </div>
                      <Button
                        size="sm"
                        variant="flat"
                        color="primary"
                        startContent={<PlusIcon className="w-3.5 h-3.5" />}
                        onPress={handleAddOption}
                        isDisabled={options.length >= 10}
                      >
                        إضافة خيار ({options.length}/10)
                      </Button>
                    </div>

                    {errors.options && (
                      <p className="text-xs text-danger font-medium flex items-center gap-1">
                        <ExclamationCircleIcon className="w-3.5 h-3.5" />
                        <span>{errors.options}</span>
                      </p>
                    )}

                    <div className="space-y-2 max-h-56 overflow-y-auto pe-1">
                      {options.map((opt, idx) => {
                        const len = opt.label_ar.length;
                        const isOverLimit = len > maxChars;

                        return (
                          <div
                            key={idx}
                            className="flex items-center gap-2 p-2 rounded-xl bg-content1 border border-divider shadow-none"
                          >
                            <span className="text-xs font-bold text-default-400 w-5 text-center">
                              {idx + 1}
                            </span>

                            {/* Label */}
                            <div className="flex-1">
                              <Input
                                size="sm"
                                variant="bordered"
                                placeholder={`نص الخيار ${idx + 1}`}
                                value={opt.label_ar}
                                onValueChange={(val) => handleOptionChange(idx, 'label_ar', val)}
                                isInvalid={isOverLimit || !!errors[`opt_label_${idx}`]}
                                errorMessage={errors[`opt_label_${idx}`]}
                                endContent={
                                  <span
                                    className={`text-[10px] ${
                                      isOverLimit ? 'text-danger font-bold' : 'text-default-400'
                                    }`}
                                  >
                                    {len}/{maxChars}
                                  </span>
                                }
                              />
                            </div>

                            {/* Value Key */}
                            <div className="w-28">
                              <Input
                                size="sm"
                                variant="bordered"
                                placeholder="رمز (Key)"
                                value={opt.value}
                                onValueChange={(val) => handleOptionChange(idx, 'value', val)}
                                isInvalid={!!errors[`opt_val_${idx}`]}
                              />
                            </div>

                            {/* Reorder Buttons */}
                            <div className="flex items-center gap-0.5">
                              <Button
                                isIconOnly
                                size="sm"
                                variant="light"
                                onPress={() => handleMoveOption(idx, 'up')}
                                isDisabled={idx === 0}
                              >
                                <ChevronUpIcon className="w-3.5 h-3.5" />
                              </Button>
                              <Button
                                isIconOnly
                                size="sm"
                                variant="light"
                                onPress={() => handleMoveOption(idx, 'down')}
                                isDisabled={idx === options.length - 1}
                              >
                                <ChevronDownIcon className="w-3.5 h-3.5" />
                              </Button>
                              <Button
                                isIconOnly
                                size="sm"
                                variant="light"
                                color="danger"
                                onPress={() => handleRemoveOption(idx)}
                                isDisabled={options.length <= 2}
                              >
                                <TrashIcon className="w-3.5 h-3.5" />
                              </Button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* Toggles */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                  <div className="flex items-center justify-between p-3 rounded-xl border border-divider bg-content2/30">
                    <div>
                      <p className="text-xs font-semibold">إجابة مطلوبة</p>
                      <p className="text-[10px] text-default-400">لا يمكن تخطيه</p>
                    </div>
                    <Switch size="sm" isSelected={isRequired} onValueChange={setIsRequired} />
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-xl border border-divider bg-content2/30">
                    <div>
                      <p className="text-xs font-semibold">استخدام كفلتر</p>
                      <p className="text-[10px] text-default-400">في طلبات التشغيل</p>
                    </div>
                    <Switch size="sm" isSelected={isFilter} onValueChange={setIsFilter} />
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-xl border border-divider bg-content2/30">
                    <div>
                      <p className="text-xs font-semibold">نشط في الاستبيان</p>
                      <p className="text-[10px] text-default-400">يرسل للسائقين</p>
                    </div>
                    <Switch size="sm" color="success" isSelected={isActive} onValueChange={setIsActive} />
                  </div>
                </div>

                {question && (
                  <div className="p-3 bg-primary-50 dark:bg-primary-950/30 rounded-xl border border-primary-200 dark:border-primary-800 text-xs text-primary-800 dark:text-primary-300">
                    <b>ملاحظة الإصدارات:</b> تعديل نص السؤال أو نوعه أو خياراته سينشئ تلقائياً <b>إصداراً جديداً (v{question.version + 1})</b>. إجابات السائقين السابقة ستظل مرتبطة بالإصدار الذي أجابوا عليه ولن تتأثر.
                  </div>
                )}
              </div>

              {/* Right Column: Live WhatsApp Mobile Preview */}
              <div className="lg:col-span-5 flex flex-col items-center justify-center border-t lg:border-t-0 lg:border-s border-divider pt-4 lg:pt-0 lg:ps-6">
                <QuestionPreview textAr={textAr} type={type} options={options} />
              </div>
            </div>
          </ModalBody>

          <ModalFooter className="border-t border-divider flex flex-col-reverse sm:flex-row gap-2 w-full safe-bottom">
            <Button
              variant="flat"
              className="w-full sm:w-auto min-h-[44px]"
              onPress={onClose}
              isDisabled={isSubmitting}
            >
              إلغاء
            </Button>
            <Button
              type="submit"
              color="primary"
              className="w-full sm:w-auto min-h-[44px]"
              isLoading={isSubmitting}
              startContent={!isSubmitting && <CheckIcon className="w-4 h-4" />}
            >
              {question ? 'حفظ التعديلات' : 'إضافة السؤال'}
            </Button>
          </ModalFooter>
        </form>
      </ModalContent>
    </Modal>
  );
};

export default QuestionModal;
