import React from 'react';
import {
  Controller,
  type Control,
  type FieldValues,
  type Path,
} from 'react-hook-form';
import { Switch, type SwitchProps } from '@heroui/react';

export interface FormSwitchProps<T extends FieldValues>
  extends Omit<SwitchProps, 'name' | 'value' | 'defaultValue' | 'isSelected' | 'onValueChange'> {
  name: Path<T>;
  control: Control<T>;
  label?: React.ReactNode;
  description?: React.ReactNode;
}

export function FormSwitch<T extends FieldValues>({
  name,
  control,
  label,
  description,
  color = 'primary',
  ...props
}: FormSwitchProps<T>) {
  return (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <div className="flex items-center justify-between gap-3">
          {(label || description) && (
            <div className="flex flex-col text-start">
              {label && <span className="text-sm font-semibold text-default-900">{label}</span>}
              {description && <span className="text-xs text-default-400">{description}</span>}
            </div>
          )}
          <Switch
            {...props}
            color={color}
            isSelected={Boolean(field.value)}
            onValueChange={field.onChange}
          />
        </div>
      )}
    />
  );
}

export default FormSwitch;
