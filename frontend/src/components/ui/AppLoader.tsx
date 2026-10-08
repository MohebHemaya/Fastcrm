import React from 'react';
import { Spinner } from '@heroui/react';
import { ar } from '@/i18n/ar';

export const AppLoader: React.FC<{ message?: string }> = ({
  message = ar.app.loading,
}) => {
  return (
    <div
      data-testid="app-loader"
      className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-background/80 backdrop-blur-md"
    >
      <div className="flex flex-col items-center gap-4 p-8 text-center">
        <Spinner size="lg" color="primary" />
        <p className="text-sm font-medium text-default-600 animate-pulse">{message}</p>
      </div>
    </div>
  );
};

export default AppLoader;
