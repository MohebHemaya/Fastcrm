import { useId } from 'react';
import {
  Controller,
  type Control,
  type FieldValues,
  type Path,
  type RegisterOptions,
} from 'react-hook-form';
import { DatePicker, type DatePickerProps } from '@heroui/react';
import { parseDateTime, type CalendarDateTime } from '@internationalized/date';

export interface FormDatePickerProps<T extends FieldValues>
  extends Omit<DatePickerProps, 'name' | 'value' | 'defaultValue' | 'onChange' | 'validate'> {
  name: Path<T>;
  control: Control<T>;
  rules?: RegisterOptions<T, Path<T>>;
}

/**
 * Safely parses various date string formats (ISO, YYYY-MM-DDTHH:mm, etc.) into CalendarDateTime
 */
export function parseDateValue(value: string | undefined | null): CalendarDateTime | null {
  if (!value) return null;
  try {
    const clean = String(value).replace('Z', '').split('.')[0];
    if (clean.includes('T')) {
      const parts = clean.split('T');
      const timePart = parts[1].length === 5 ? `${parts[1]}:00` : parts[1];
      return parseDateTime(`${parts[0]}T${timePart.slice(0, 8)}`);
    }
    return parseDateTime(`${clean}T09:00:00`);
  } catch {
    return null;
  }
}

export function FormDatePicker<T extends FieldValues>({
  name,
  control,
  rules,
  label,
  isRequired,
  variant = 'bordered',
  granularity = 'minute',
  hourCycle = 12,
  hideTimeZone = true,
  showMonthAndYearPickers = true,
  id,
  ...props
}: FormDatePickerProps<T>) {
  const generatedId = useId();
  const inputId = id || `form-date-picker-${String(name)}-${generatedId}`;

  return (
    <div className="w-full flex flex-col gap-1.5 text-start">
      {label && (
        <label
          htmlFor={inputId}
          className="text-sm font-semibold text-foreground-700 flex items-center gap-1 select-none"
        >
          <span>{label}</span>
          {isRequired && <span className="text-danger font-bold text-xs">*</span>}
        </label>
      )}

      <Controller
        name={name}
        control={control}
        rules={rules}
        render={({ field, fieldState }) => {
          const parsedValue = parseDateValue(field.value);

          return (
            <div className="relative w-full">
              <DatePicker
                {...(props as any)}
                aria-label="اختيار الموعد والوقت"
                variant={variant}
                granularity={granularity}
                hourCycle={hourCycle}
                hideTimeZone={hideTimeZone}
                showMonthAndYearPickers={showMonthAndYearPickers}
                value={parsedValue as any}
                onChange={(dateValue: any) => {
                  if (!dateValue) {
                    field.onChange('');
                    return;
                  }
                  // Format as YYYY-MM-DDTHH:mm for standard datetime-local form payload
                  const str = dateValue.toString();
                  const formatted = str.slice(0, 16);
                  field.onChange(formatted);
                }}
                isInvalid={Boolean(fieldState.error)}
                errorMessage={fieldState.error?.message}
                classNames={{
                  base: 'w-full !mt-0',
                  calendar: 'bg-background border border-default-200 shadow-lg rounded-xl',
                  popoverContent: 'bg-background border border-default-200 shadow-xl rounded-xl p-2',
                  ...props.classNames,
                }}
              />

              {/* Hidden synchronization input for tests, automated tools, and native HTML form submission */}
              <input
                id={inputId}
                type="datetime-local"
                className="sr-only"
                tabIndex={-1}
                aria-label={typeof label === 'string' ? label : String(name)}
                value={field.value || ''}
                onChange={(e) => field.onChange(e.target.value)}
              />
            </div>
          );
        }}
      />
    </div>
  );
}

export default FormDatePicker;
