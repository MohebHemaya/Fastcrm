import React from 'react';
import {
  Controller,
  type Control,
  type FieldValues,
  type Path,
  type RegisterOptions,
} from 'react-hook-form';
import { Select, SelectItem, type SelectProps } from '@heroui/react';

export interface SelectOption {
  key: string | number;
  label: string;
  description?: string;
}

export interface FormSelectProps<T extends FieldValues>
  extends Omit<SelectProps, 'name' | 'value' | 'defaultValue' | 'children' | 'selectedKeys'> {
  name: Path<T>;
  control: Control<T>;
  rules?: RegisterOptions<T, Path<T>>;
  options?: SelectOption[];
  children?: React.ReactNode;
}

export function FormSelect<T extends FieldValues>({
  name,
  control,
  rules,
  label,
  isRequired,
  options,
  children,
  variant = 'bordered',
  labelPlacement = 'outside',
  id,
  ...props
}: FormSelectProps<T>) {
  const selectId = id || `form-select-${String(name)}`;

  return (
    <div className="w-full flex flex-col gap-1.5 text-start">
      {label && (
        <label
          htmlFor={selectId}
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
          const selectedKey =
            field.value !== undefined && field.value !== null && field.value !== ''
              ? String(field.value)
              : '';

          return (
            <Select
              {...props}
              id={selectId}
              aria-label={typeof label === 'string' ? label : (props['aria-label'] || String(name))}
              variant={variant}
              selectedKeys={selectedKey ? [selectedKey] : []}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                field.onChange(selected !== undefined ? String(selected) : '');
              }}
              isInvalid={Boolean(fieldState.error)}
              errorMessage={fieldState.error?.message}
              classNames={{
                base: 'w-full !mt-0',
                trigger: 'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-200 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary',
              }}
            >
            {options
              ? options.map((opt) => (
                  <SelectItem key={String(opt.key)} textValue={opt.label}>
                    {opt.label}
                  </SelectItem>
                ))
              : (children as any)}
          </Select>
        );
      }}
    />
    </div>
  );
}

export default FormSelect;
