import React from 'react';
import { Chip, type ChipProps } from '@heroui/react';
import type { DriverStatusValue } from '@/types/driver';
import { ar } from '@/i18n/ar';

interface DriverStatusBadgeProps {
  status: DriverStatusValue;
  label?: string;
  size?: ChipProps['size'];
  variant?: ChipProps['variant'];
  className?: string;
}

export const DriverStatusBadge: React.FC<DriverStatusBadgeProps> = ({
  status,
  label,
  size = 'sm',
  variant = 'flat',
  className = '',
}) => {
  const getStatusConfig = (
    val: DriverStatusValue
  ): { color: ChipProps['color']; text: string; extraClass?: string } => {
    switch (val) {
      case 'new':
        return { color: 'primary', text: ar.statuses.new };
      case 'contacted':
        return { color: 'secondary', text: ar.statuses.contacted };
      case 'qualified':
        return { color: 'secondary', text: ar.statuses.qualified };
      case 'training_scheduled':
        return { color: 'warning', text: ar.statuses.training_scheduled };
      case 'trained':
        return { color: 'primary', text: ar.statuses.trained };
      case 'active':
        return {
          color: 'success',
          text: ar.statuses.active,
          extraClass:
            'badge-status-active !bg-emerald-50 !text-emerald-700 dark:!bg-emerald-950/40 dark:!text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60 font-semibold',
        };
      case 'declined':
        return { color: 'default', text: ar.statuses.declined };
      case 'no_response':
        return { color: 'warning', text: ar.statuses.no_response };
      case 'suspended':
        return { color: 'danger', text: ar.statuses.suspended };
      case 'blocked':
        return { color: 'danger', text: ar.statuses.blocked };
      case 'opted_out':
        return { color: 'danger', text: ar.statuses.opted_out };
      default:
        return { color: 'default', text: val };
    }
  };

  const config = getStatusConfig(status);

  return (
    <Chip
      color={config.color}
      size={size}
      variant={variant}
      className={`font-medium ${config.extraClass || ''} ${className}`.trim()}
    >
      {label || config.text}
    </Chip>
  );
};

export default DriverStatusBadge;
