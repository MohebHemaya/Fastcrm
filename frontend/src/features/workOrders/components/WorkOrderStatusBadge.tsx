import React from 'react';
import { Chip } from '@heroui/react';
import {
  ClockIcon,
  PaperAirplaneIcon,
  UsersIcon,
  CheckCircleIcon,
  PlayIcon,
  XCircleIcon,
} from '@/components/icons';
import type { WorkOrderStatus } from '@/types/workOrder';
import { ar } from '@/i18n/ar';

interface WorkOrderStatusBadgeProps {
  status: WorkOrderStatus;
  size?: 'sm' | 'md' | 'lg';
}

export const WorkOrderStatusBadge: React.FC<WorkOrderStatusBadgeProps> = ({
  status,
  size = 'sm',
}) => {
  const config: Record<
    WorkOrderStatus,
    {
      label: string;
      color: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
      icon: React.ReactNode;
    }
  > = {
    draft: {
      label: ar.workOrders.status.draft,
      color: 'default',
      icon: <ClockIcon className="w-3.5 h-3.5" />,
    },
    sending: {
      label: ar.workOrders.status.sending,
      color: 'warning',
      icon: <PaperAirplaneIcon className="w-3.5 h-3.5 rtl:-scale-x-100" />,
    },
    filling: {
      label: ar.workOrders.status.filling,
      color: 'primary',
      icon: <UsersIcon className="w-3.5 h-3.5" />,
    },
    filled: {
      label: ar.workOrders.status.filled,
      color: 'success',
      icon: <CheckCircleIcon className="w-3.5 h-3.5" />,
    },
    in_progress: {
      label: ar.workOrders.status.in_progress,
      color: 'secondary',
      icon: <PlayIcon className="w-3.5 h-3.5 rtl:rotate-180" />,
    },
    completed: {
      label: ar.workOrders.status.completed,
      color: 'success',
      icon: <CheckCircleIcon className="w-3.5 h-3.5" />,
    },
    cancelled: {
      label: ar.workOrders.status.cancelled,
      color: 'danger',
      icon: <XCircleIcon className="w-3.5 h-3.5" />,
    },
  };

  const item = config[status] || config.draft;

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

export default WorkOrderStatusBadge;
