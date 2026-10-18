import React from 'react';

/**
 * Premium skeleton placeholder displayed during route transitions.
 * Conforms to RTL logical properties and Fast Express styling.
 */
export const PageSkeleton: React.FC = () => {
  return (
    <div className="w-full space-y-6 animate-pulse p-4 sm:p-6" data-testid="page-skeleton">
      {/* Header bar placeholder */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-divider">
        <div className="space-y-2">
          <div className="h-7 w-48 bg-default-200 dark:bg-default-100 rounded-lg" />
          <div className="h-4 w-72 bg-default-100 dark:bg-default-50 rounded-md" />
        </div>
        <div className="flex items-center gap-2">
          <div className="h-9 w-24 bg-default-200 dark:bg-default-100 rounded-lg" />
          <div className="h-9 w-28 bg-default-200 dark:bg-default-100 rounded-lg" />
        </div>
      </div>

      {/* Metric / Stat cards placeholder */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div
            key={i}
            className="p-4 rounded-xl border border-divider bg-content1/50 backdrop-blur-sm space-y-3"
          >
            <div className="flex items-center justify-between">
              <div className="h-4 w-20 bg-default-200 rounded" />
              <div className="h-8 w-8 rounded-full bg-default-200" />
            </div>
            <div className="h-6 w-16 bg-default-300 rounded" />
            <div className="h-3 w-32 bg-default-100 rounded" />
          </div>
        ))}
      </div>

      {/* Main content / Table placeholder */}
      <div className="rounded-xl border border-divider bg-content1/50 p-6 space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-divider/60">
          <div className="h-5 w-36 bg-default-200 rounded" />
          <div className="h-8 w-44 bg-default-200 rounded-lg" />
        </div>
        <div className="space-y-3 pt-2">
          {[1, 2, 3, 4, 5].map((row) => (
            <div key={row} className="flex items-center gap-4 py-2">
              <div className="h-4 w-12 bg-default-200 rounded" />
              <div className="h-4 w-32 bg-default-200 rounded" />
              <div className="h-4 w-40 bg-default-100 rounded" />
              <div className="h-4 w-24 bg-default-200 rounded ms-auto" />
              <div className="h-7 w-16 bg-default-200 rounded-lg" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default PageSkeleton;
