import {
  Controller,
  type Control,
  type FieldValues,
  type Path,
  type RegisterOptions,
} from 'react-hook-form';
import { Input, type InputProps } from '@heroui/react';

export interface FormInputProps<T extends FieldValues>
  extends Omit<InputProps, 'name' | 'value' | 'defaultValue'> {
  name: Path<T>;
  control: Control<T>;
  rules?: RegisterOptions<T, Path<T>>;
  inputDir?: 'rtl' | 'ltr' | 'auto';
}

export function FormInput<T extends FieldValues>({
  name,
  control,
  rules,
  label,
  isRequired,
  variant = 'bordered',
  labelPlacement = 'outside',
  inputDir,
  classNames,
  type = 'text',
  id,
  ...props
}: FormInputProps<T>) {
  // Determine if text direction should be forced LTR (for phone numbers, numeric codes, numbers)
  const isLtrType = type === 'tel' || type === 'number' || inputDir === 'ltr';
  const effectiveDir = inputDir || (isLtrType ? 'ltr' : undefined);
  const inputId = id || `form-input-${String(name)}`;

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
        render={({ field, fieldState }) => (
          <Input
            {...field}
            id={inputId}
            aria-label={typeof label === 'string' ? label : (props['aria-label'] || String(name))}
            value={field.value ?? ''}
            onChange={(e) => {
              const val =
                type === 'number'
                  ? e.target.value === ''
                    ? ''
                    : Number(e.target.value)
                  : e.target.value;
              field.onChange(val);
            }}
            type={type}
            variant={variant}
            isInvalid={Boolean(fieldState.error)}
            errorMessage={fieldState.error?.message}
            dir={effectiveDir}
            classNames={{
              ...classNames,
              base: `w-full !mt-0 ${classNames?.base || ''}`,
              inputWrapper: `h-10 min-h-10 border-1.5 border-default-300 dark:border-default-200 bg-background shadow-xs hover:border-default-400 group-data-[focus=true]:border-primary ${classNames?.inputWrapper || ''}`,
              input: `${effectiveDir ? `text-${effectiveDir}` : ''} placeholder:text-default-400 text-small ${classNames?.input || ''}`,
            }}
            {...props}
          />
        )}
      />
    </div>
  );
}

export default FormInput;
