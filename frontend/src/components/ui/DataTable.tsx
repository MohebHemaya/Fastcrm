import React from 'react';
import {
  ArrowsUpDownIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ChevronLeftIcon,
} from '@/components/icons';
import { Button, Spinner } from '@heroui/react';
import EmptyState from './EmptyState';
import { useIsMobile } from '@/hooks/useMediaQuery';
import { ar } from '@/i18n/ar';

export interface ColumnDef<T> {
  id: string;
  header: React.ReactNode;
  accessor?: keyof T;
  render?: (row: T, index: number) => React.ReactNode;
  sortable?: boolean;
  className?: string;
  headerClassName?: string;
  isPrimary?: boolean;
}

export interface DataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  keyExtractor: (row: T, index: number) => string | number;
  isLoading?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyIcon?: React.ReactNode;
  topContent?: React.ReactNode;
  renderCard?: (row: T, index: number) => React.ReactNode;
  // Pagination
  currentPage?: number;
  lastPage?: number;
  perPage?: number;
  total?: number;
  onPageChange?: (page: number) => void;
  // Sorting
  sortBy?: string;
  sortDirection?: 'asc' | 'desc';
  onSort?: (columnId: string) => void;
  className?: string;
}

export function DataTable<T>({
  columns,
  data,
  keyExtractor,
  isLoading = false,
  emptyTitle = ar.common.noData,
  emptyDescription,
  emptyIcon,
  topContent,
  renderCard,
  currentPage = 1,
  lastPage = 1,
  total,
  onPageChange,
  sortBy,
  sortDirection,
  onSort,
  className = '',
}: DataTableProps<T>): React.ReactElement {
  const isMobile = useIsMobile();

  const handleSort = (col: ColumnDef<T>) => {
    if (!col.sortable || !onSort) return;
    onSort(col.id);
  };

  const primaryCol = columns.find((c) => c.isPrimary) || columns[0];
  const actionCol = columns.find((c) => c.id === 'actions' || c.id === 'action');
  const bodyCols = columns.filter((c) => c !== primaryCol && c !== actionCol);

  const renderPagination = () => {
    if (!onPageChange) return null;
    return (
      <div className="flex flex-col items-center justify-between gap-3 border-t border-default-200 ps-4 pe-4 py-3 sm:flex-row">
        <div className="text-xs text-default-500 text-center sm:text-start">
          {total !== undefined && (
            <span>
              {ar.common.total}: <strong className="font-semibold text-default-800">{total}</strong>
            </span>
          )}
          {lastPage > 1 && (
            <span className="ms-3">
              {ar.common.page} <strong className="font-semibold text-default-800">{currentPage}</strong> {ar.common.of}{' '}
              <strong className="font-semibold text-default-800">{lastPage}</strong>
            </span>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="flat"
            color="default"
            isDisabled={currentPage <= 1 || isLoading}
            onPress={() => onPageChange(currentPage - 1)}
            className="min-h-[44px] min-w-[44px]"
            startContent={<ChevronRightIcon className="h-4 w-4 rtl:rotate-0 ltr:rotate-180" />}
          >
            {ar.common.previous}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="default"
            isDisabled={currentPage >= lastPage || isLoading}
            onPress={() => onPageChange(currentPage + 1)}
            className="min-h-[44px] min-w-[44px]"
            endContent={<ChevronLeftIcon className="h-4 w-4 rtl:rotate-0 ltr:rotate-180" />}
          >
            {ar.common.next}
          </Button>
        </div>
      </div>
    );
  };

  return (
    <div className={`flex flex-col gap-4 ${className}`}>
      {topContent && <div className="flex flex-col gap-3">{topContent}</div>}

      {/* Mobile Card View (< 768px) */}
      {isMobile ? (
        <div data-testid="data-table-cards" className="flex flex-col gap-3">
          {isLoading ? (
            <div className="rounded-xl border border-default-200 bg-content1 p-12 text-center shadow-sm">
              <div className="flex flex-col items-center justify-center gap-3">
                <Spinner size="lg" color="primary" />
                <p className="text-sm text-default-500">{ar.app.loading}</p>
              </div>
            </div>
          ) : data.length === 0 ? (
            <div className="rounded-xl border border-default-200 bg-content1 p-8 shadow-sm">
              <EmptyState
                icon={emptyIcon}
                title={emptyTitle}
                description={emptyDescription}
              />
            </div>
          ) : (
            data.map((row, index) => {
              if (renderCard) {
                return (
                  <div key={keyExtractor(row, index)} data-testid="data-table-card">
                    {renderCard(row, index)}
                  </div>
                );
              }

              return (
                <div
                  key={keyExtractor(row, index)}
                  data-testid="data-table-card"
                  className="rounded-xl border border-default-200 bg-content1 p-4 shadow-sm space-y-3 transition-colors hover:border-default-300"
                >
                  {/* Card Header: Primary info + Actions */}
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2 pb-2 border-b border-default-100">
                    <div className="flex-1 font-semibold text-sm text-default-900 break-words min-w-0">
                      {primaryCol?.render
                        ? primaryCol.render(row, index)
                        : primaryCol?.accessor
                        ? String(row[primaryCol.accessor] ?? '')
                        : null}
                    </div>
                    {actionCol && (
                      <div className="pt-1 sm:pt-0 overflow-x-auto">
                        {actionCol.render
                          ? actionCol.render(row, index)
                          : actionCol.accessor
                          ? String(row[actionCol.accessor] ?? '')
                          : null}
                      </div>
                    )}
                  </div>

                  {/* Card Body: Label/Value pairs */}
                  <div className="grid grid-cols-1 gap-2 text-xs">
                    {bodyCols.map((col) => {
                      const val = col.render
                        ? col.render(row, index)
                        : col.accessor
                        ? String(row[col.accessor] ?? '')
                        : null;

                      if (val === null || val === undefined || val === '') return null;

                      return (
                        <div key={col.id} className="flex items-center justify-between gap-2 py-0.5">
                          <span className="text-default-400 font-medium shrink-0">{col.header}:</span>
                          <div className="text-default-700 font-medium text-end break-words min-w-0">
                            {val}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })
          )}

          {/* Pagination bar for mobile */}
          <div className="rounded-xl border border-default-200 bg-content1 shadow-sm">
            {renderPagination()}
          </div>
        </div>
      ) : (
        /* Desktop Table View (>= 768px) */
        <div data-testid="data-table-desktop" className="overflow-hidden rounded-xl border border-default-200 bg-content1 shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full min-w-full divide-y divide-default-200 text-start text-sm">
              <thead className="bg-default-50/80">
                <tr>
                  {columns.map((col) => {
                    const isSorted = sortBy === col.id;
                    const isTextEnd = col.headerClassName?.includes('text-end');
                    return (
                      <th
                        key={col.id}
                        scope="col"
                        className={`ps-4 pe-4 py-3.5 ${isTextEnd ? 'text-end' : 'text-start'} font-semibold text-default-700 ${
                          col.sortable ? 'cursor-pointer select-none hover:bg-default-100/70' : ''
                        } ${col.headerClassName || ''}`}
                        onClick={() => handleSort(col)}
                      >
                        <div className={`flex items-center gap-1.5 ${isTextEnd ? 'justify-end' : ''}`}>
                          <span>{col.header}</span>
                          {col.sortable && (
                            <span className="text-default-400">
                              {isSorted ? (
                                sortDirection === 'asc' ? (
                                  <ChevronUpIcon className="h-3.5 w-3.5 text-primary" />
                                ) : (
                                  <ChevronDownIcon className="h-3.5 w-3.5 text-primary" />
                                )
                              ) : (
                                <ArrowsUpDownIcon className="h-3.5 w-3.5 opacity-40 hover:opacity-100" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              </thead>

              <tbody className="divide-y divide-default-100 bg-content1">
                {isLoading ? (
                  <tr>
                    <td colSpan={columns.length} className="py-16 text-center">
                      <div className="flex flex-col items-center justify-center gap-3">
                        <Spinner size="lg" color="primary" />
                        <p className="text-sm text-default-500">{ar.app.loading}</p>
                      </div>
                    </td>
                  </tr>
                ) : data.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length} className="py-12">
                      <EmptyState
                        icon={emptyIcon}
                        title={emptyTitle}
                        description={emptyDescription}
                      />
                    </td>
                  </tr>
                ) : (
                  data.map((row, index) => (
                    <tr
                      key={keyExtractor(row, index)}
                      className="transition-colors hover:bg-default-50/60"
                    >
                      {columns.map((col) => (
                        <td
                          key={col.id}
                          className={`ps-4 pe-4 py-3.5 text-default-700 ${col.className || ''}`}
                        >
                          {col.render
                            ? col.render(row, index)
                            : col.accessor
                            ? String(row[col.accessor] ?? '')
                            : null}
                        </td>
                      ))}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination bar */}
          {renderPagination()}
        </div>
      )}
    </div>
  );
}

export default DataTable;
