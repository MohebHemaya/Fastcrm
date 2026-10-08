import React from 'react';
import { InboxIcon } from '@/components/icons';

export interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
  className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  description,
  action,
  className = '',
}) => {
  return (
    <div
      className={`flex flex-col items-center justify-center rounded-2xl border border-dashed border-default-200 bg-default-50/50 p-8 text-center transition-all sm:p-12 ${className}`}
      data-testid="empty-state"
    >
      <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-default-100 text-default-400">
        {icon || <InboxIcon className="h-8 w-8" />}
      </div>
      <h3 className="text-lg font-semibold text-default-900">{title}</h3>
      {description && (
        <p className="mt-1.5 max-w-sm text-sm text-default-500">
          {description}
        </p>
      )}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
};

export default EmptyState;
