import {
  Controller,
  type Control,
  type FieldValues,
  type Path,
  type RegisterOptions,
} from 'react-hook-form';
import { Textarea, type TextAreaProps } from '@heroui/react';

export interface FormTextareaProps<T extends FieldValues>
  extends Omit<TextAreaProps, 'name' | 'value' | 'defaultValue'> {
  name: Path<T>;
  control: Control<T>;
  rules?: RegisterOptions<T, Path<T>>;
}

export function FormTextarea<T extends FieldValues>({
  name,
  control,
  rules,
  label,
  isRequired,
  variant = 'bordered',
  labelPlacement = 'outside',
  minRows = 3,
  id,
  classNames,
  ...props
}: FormTextareaProps<T>) {
  const textareaId = id || `form-textarea-${String(name)}`;

  return (
    <div className="w-full flex flex-col gap-1.5 text-start">
      {label && (
        <label
          htmlFor={textareaId}
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
          <Textarea
            {...field}
            id={textareaId}
            aria-label={typeof label === 'string' ? label : (props['aria-label'] || String(name))}
            value={field.value ?? ''}
            onChange={(e) => field.onChange(e.target.value)}
            variant={variant}
            minRows={minRows}
            isInvalid={Boolean(fieldState.error)}
            errorMessage={fieldState.error?.message}
            classNames={{
              ...classNames,
              base: `w-full !mt-0 ${classNames?.base || ''}`,
              inputWrapper: `border-1.5 border-default-300 dark:border-default-200 bg-background shadow-xs hover:border-default-400 group-data-[focus=true]:border-primary ${classNames?.inputWrapper || ''}`,
              input: `placeholder:text-default-400 text-small ${classNames?.input || ''}`,
            }}
            {...props}
          />
        )}
      />
    </div>
  );
}

export default FormTextarea;
