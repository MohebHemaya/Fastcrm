import React from 'react';
import { Button } from '@heroui/react';
import ar from '@/i18n/ar';

interface OpenSlotsBannerProps {
  openSlotsCount: number;
  onPromote: () => void;
  onAddDriver: () => void;
  onResendOffers: () => void;
}

export const OpenSlotsBanner: React.FC<OpenSlotsBannerProps> = ({
  openSlotsCount,
  onPromote,
  onAddDriver,
  onResendOffers,
}) => {
  if (openSlotsCount <= 0) {
    return null;
  }

  return (
    <div
      data-testid="open-slots-banner"
      className="p-4 rounded-xl border border-warning-200 bg-warning-50 text-warning-900 shadow-none"
    >
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-start gap-3">
          <svg
            className="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          <div>
            <h4 className="font-bold text-warning-900 text-base">
              {ar.workOrders.openSlotsBanner.title} ({openSlotsCount} مكان شاغر)
            </h4>
            <p className="text-sm text-warning-800 mt-1">
              {ar.workOrders.openSlotsBanner.description}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            size="sm"
            color="warning"
            variant="solid"
            className="font-medium"
            onPress={onPromote}
          >
            {ar.workOrders.openSlotsBanner.promoteButton}
          </Button>

          <Button
            size="sm"
            color="primary"
            variant="flat"
            className="font-medium"
            onPress={onAddDriver}
          >
            {ar.workOrders.openSlotsBanner.addDriverButton}
          </Button>

          <Button
            size="sm"
            color="default"
            variant="bordered"
            className="font-medium"
            onPress={onResendOffers}
          >
            {ar.workOrders.openSlotsBanner.resendButton}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default OpenSlotsBanner;
