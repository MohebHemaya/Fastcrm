import React from 'react';
import { Chip } from '@heroui/react';
import {
  ClockIcon,
  PaperAirplaneIcon,
  CheckIcon,
  ShieldCheckIcon,
  XMarkIcon,
  ExclamationTriangleIcon,
  ExclamationCircleIcon,
  NoSymbolIcon,
} from '@/components/icons';
import type { WorkOrderRecipientStatus } from '@/types/workOrder';
import { ar } from '@/i18n/ar';

interface WorkOrderRecipientStatusBadgeProps {
  status: WorkOrderRecipientStatus;
  size?: 'sm' | 'md' | 'lg';
}

export const WorkOrderRecipientStatusBadge: React.FC<WorkOrderRecipientStatusBadgeProps> = ({
  status,
  size = 'sm',
}) => {
  const config: Record<
    WorkOrderRecipientStatus,
    {
      label: string;
      color: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
      icon: React.ReactNode;
    }
  > = {
    pending: {
      label: ar.workOrders.recipientStatus.pending,
      color: 'default',
      icon: <ClockIcon className="w-3.5 h-3.5" />,
    },
    sent: {
      label: ar.workOrders.recipientStatus.sent,
      color: 'primary',
      icon: <PaperAirplaneIcon className="w-3.5 h-3.5 rtl:-scale-x-100" />,
    },
    confirmed: {
      label: ar.workOrders.recipientStatus.confirmed,
      color: 'success',
      icon: <CheckIcon className="w-3.5 h-3.5" />,
    },
    backup: {
      label: ar.workOrders.recipientStatus.backup,
      color: 'warning',
      icon: <ShieldCheckIcon className="w-3.5 h-3.5" />,
    },
    declined: {
      label: ar.workOrders.recipientStatus.declined,
      color: 'danger',
      icon: <XMarkIcon className="w-3.5 h-3.5" />,
    },
    too_late: {
      label: ar.workOrders.recipientStatus.too_late,
      color: 'default',
      icon: <ExclamationTriangleIcon className="w-3.5 h-3.5" />,
    },
    no_response: {
      label: ar.workOrders.recipientStatus.no_response,
      color: 'default',
      icon: <ClockIcon className="w-3.5 h-3.5" />,
    },
    failed: {
      label: ar.workOrders.recipientStatus.failed,
      color: 'danger',
      icon: <ExclamationCircleIcon className="w-3.5 h-3.5" />,
    },
    cancelled: {
      label: ar.workOrders.recipientStatus.cancelled,
      color: 'default',
      icon: <NoSymbolIcon className="w-3.5 h-3.5" />,
    },
  };

  const item = config[status] || config.pending;

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

export default WorkOrderRecipientStatusBadge;
