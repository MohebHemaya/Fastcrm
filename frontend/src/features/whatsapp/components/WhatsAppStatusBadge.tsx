import React from 'react';
import { Chip } from '@heroui/react';
import { CheckIcon, ClockIcon, ExclamationCircleIcon } from '@/components/icons';
import type { WhatsAppStatus } from '@/types/whatsapp';
import { ar } from '@/i18n/ar';

interface WhatsAppStatusBadgeProps {
  status: WhatsAppStatus;
  size?: 'sm' | 'md' | 'lg';
}

export const WhatsAppStatusBadge: React.FC<WhatsAppStatusBadgeProps> = ({
  status,
  size = 'sm',
}) => {
  const config: Record<
    WhatsAppStatus,
    { label: string; color: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger'; icon: React.ReactNode }
  > = {
    queued: {
      label: ar.whatsapp.status.queued,
      color: 'warning',
      icon: <ClockIcon className="w-3 h-3 text-warning-600" />,
    },
    sent: {
      label: ar.whatsapp.status.sent,
      color: 'default',
      icon: <CheckIcon className="w-3 h-3 text-default-500" />,
    },
    delivered: {
      label: ar.whatsapp.status.delivered,
      color: 'primary',
      icon: (
        <div className="inline-flex -space-x-1">
          <CheckIcon className="w-3 h-3 text-primary-500" />
          <CheckIcon className="w-3 h-3 text-primary-500" />
        </div>
      ),
    },
    read: {
      label: ar.whatsapp.status.read,
      color: 'success',
      icon: (
        <div className="inline-flex -space-x-1">
          <CheckIcon className="w-3 h-3 text-blue-500" />
          <CheckIcon className="w-3 h-3 text-blue-500" />
        </div>
      ),
    },
    failed: {
      label: ar.whatsapp.status.failed,
      color: 'danger',
      icon: <ExclamationCircleIcon className="w-3 h-3 text-danger-500" />,
    },
  };

  const item = config[status] || config.queued;

  return (
    <Chip
      size={size}
      color={item.color}
      variant="flat"
      startContent={item.icon}
      className="font-medium"
    >
      {item.label}
    </Chip>
  );
};

export default WhatsAppStatusBadge;
