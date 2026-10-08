import React from 'react';

export interface PageContainerProps {
  children: React.ReactNode;
  className?: string;
  maxWidth?: string;
}

export const PageContainer: React.FC<PageContainerProps> = ({
  children,
  className = '',
  maxWidth = 'max-w-7xl',
}) => {
  return (
    <div
      className={`mx-auto w-full ${maxWidth} ps-3.5 pe-3.5 sm:ps-6 sm:pe-6 lg:ps-8 lg:pe-8 py-4 sm:py-6 safe-bottom ${className}`}
    >
      {children}
    </div>
  );
};

export default PageContainer;
