import React from 'react';
import { CheckIcon, XMarkIcon } from '@/components/icons';
import type { WorkOrderStatus } from '@/types/workOrder';

interface OrderStatusStepperProps {
  status: WorkOrderStatus | string;
  className?: string;
}

interface StepItem {
  id: number;
  key: string;
  label: string;
}

const STEPS: StepItem[] = [
  { id: 1, key: 'draft', label: 'مسودة' },
  { id: 2, key: 'sending', label: 'جاري الإرسال' },
  { id: 3, key: 'filled', label: 'مكتمل العدد' },
  { id: 4, key: 'in_progress', label: 'قيد التنفيذ' },
  { id: 5, key: 'completed', label: 'منتهية' },
];

function getActiveStepIndex(status: string): number {
  switch (status) {
    case 'draft':
      return 1;
    case 'sending':
    case 'filling':
      return 2;
    case 'filled':
      return 3;
    case 'in_progress':
      return 4;
    case 'completed':
      return 5;
    default:
      return 1;
  }
}

export const OrderStatusStepper: React.FC<OrderStatusStepperProps> = ({ status, className = '' }) => {
  const isCancelled = status === 'cancelled';
  const activeStep = getActiveStepIndex(status);

  if (isCancelled) {
    return (
      <div className={`p-3 rounded-xl bg-danger-50 border border-danger-200 flex items-center justify-between gap-3 ${className}`}>
        <div className="flex items-center gap-2.5">
          <XMarkIcon className="w-5 h-5 text-danger shrink-0" />
          <div className="flex flex-col text-start">
            <span className="text-xs font-bold text-danger-700">أمر الشغل ملغي</span>
            <span className="text-[11px] text-danger-600/80">تم إلغاء هذا الأمر وتوقف استلام التأكيدات</span>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`w-full p-3.5 sm:p-4 rounded-xl bg-background border border-default-200 ${className}`}>
      <div className="flex items-center justify-between relative">
        {STEPS.map((step, idx) => {
          const isCompleted = activeStep > step.id;
          const isCurrent = activeStep === step.id;
          const isLast = idx === STEPS.length - 1;

          return (
            <React.Fragment key={step.id}>
              {/* Step Node */}
              <div className="flex flex-col items-center gap-1.5 z-10 select-none">
                <div
                  className={`w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-xs font-bold transition-colors duration-150 ${
                    isCompleted
                      ? 'bg-success text-white'
                      : isCurrent
                      ? 'bg-primary text-white'
                      : 'bg-default-100 dark:bg-default-800 text-default-400 border border-default-200 dark:border-default-700'
                  }`}
                >
                  {isCompleted ? (
                    <CheckIcon className="w-4 h-4 stroke-[3]" />
                  ) : (
                    <span>{step.id}</span>
                  )}
                </div>

                <span
                  className={`text-[11px] sm:text-xs font-medium text-center whitespace-nowrap transition-colors ${
                    isCurrent
                      ? 'text-primary font-bold'
                      : isCompleted
                      ? 'text-foreground'
                      : 'text-default-400'
                  }`}
                >
                  {step.label}
                </span>
              </div>

              {/* Connecting Bar */}
              {!isLast && (
                <div className="flex-1 h-0.5 sm:h-1 mx-1.5 -mt-5 transition-colors duration-150 rounded-full overflow-hidden bg-default-200 dark:bg-default-800">
                  <div
                    className={`h-full transition-all duration-200 rounded-full ${
                      activeStep > step.id
                        ? 'w-full bg-success'
                        : activeStep === step.id
                        ? 'w-1/2 bg-primary'
                        : 'w-0'
                    }`}
                  />
                </div>
              )}
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
};

export default OrderStatusStepper;
