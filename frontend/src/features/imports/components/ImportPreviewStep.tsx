import React, { useState, useEffect, useCallback } from 'react';
import {
  Tabs,
  Tab,
  Button,
  Chip,
} from '@heroui/react';
import {
  ArrowDownTrayIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@/components/icons';
import { SearchInput } from '@/components/form';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import importService from '@/services/importService';
import type { ImportItem, ImportRow } from '@/types/import';

interface ImportPreviewStepProps {
  importItem: ImportItem;
  onCommitStarted: (item: ImportItem) => void;
  onCancel: () => void;
}

export const ImportPreviewStep: React.FC<ImportPreviewStepProps> = ({
  importItem,
  onCommitStarted,
  onCancel,
}) => {
  const [selectedTab, setSelectedTab] = useState<string>('new');
  const [rows, setRows] = useState<ImportRow[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  // Modals state
  const [isCancelConfirmOpen, setIsCancelConfirmOpen] = useState(false);
  const [isCommitConfirmOpen, setIsCommitConfirmOpen] = useState(false);
  const [isActionLoading, setIsActionLoading] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  // Debounce search
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(search);
      setCurrentPage(1);
    }, 300);
    return () => clearTimeout(handler);
  }, [search]);

  // Map selectedTab to backend classification param
  const getClassificationParam = (tab: string): string => {
    switch (tab) {
      case 'new':
        return 'new';
      case 'duplicate':
        return 'duplicate_in_file'; // or backend handles comma-separated or tab filters
      case 'active':
        return 'active';
      case 'invalid':
        return 'invalid';
      default:
        return 'new';
    }
  };

  const fetchRows = useCallback(async () => {
    setIsLoading(true);
    try {
      const classification = getClassificationParam(selectedTab);
      const res = await importService.getImportRows(importItem.id, {
        classification,
        search: debouncedSearch || undefined,
        page: currentPage,
        per_page: 15,
      });

      setRows(res.data);
      setCurrentPage(res.meta.current_page);
      setLastPage(res.meta.last_page);
      setTotal(res.meta.total);
    } catch (err) {
      console.error('Failed to load import rows', err);
    } finally {
      setIsLoading(false);
    }
  }, [importItem.id, selectedTab, debouncedSearch, currentPage]);

  useEffect(() => {
    fetchRows();
  }, [fetchRows]);

  const handleTabChange = (key: React.Key) => {
    setSelectedTab(String(key));
    setSearch('');
    setDebouncedSearch('');
    setCurrentPage(1);
  };

  const handleCancelImport = async () => {
    setIsActionLoading(true);
    try {
      await importService.cancelImport(importItem.id);
      addToast({
        title: 'تم إلغاء الاستيراد بنجاح',
        message: 'تم إلغاء عملية الاستيراد وحذف السجلات المؤقتة.',
        type: 'info',
      });
      setIsCancelConfirmOpen(false);
      onCancel();
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsActionLoading(false);
    }
  };

  const handleCommitImport = async () => {
    setIsActionLoading(true);
    try {
      const res = await importService.commitImport(importItem.id);
      setIsCommitConfirmOpen(false);
      onCommitStarted(res.data);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsActionLoading(false);
    }
  };

  const counts = importItem.counts;
  const duplicateTotal = (counts?.duplicate_in_file || 0) + (counts?.existing || 0);

  const columns: ColumnDef<ImportRow>[] = [
    {
      id: 'row_number',
      header: ar.imports.rowNumber,
      render: (row) => <span className="font-mono text-default-400 text-xs">{row.row_number}</span>,
    },
    {
      id: 'raw_phone',
      header: ar.imports.rawPhone,
      isPrimary: true,
      render: (row) => (
        <span className="font-mono text-sm font-semibold" dir="ltr">
          {row.raw_phone}
        </span>
      ),
    },
    {
      id: 'normalized_phone',
      header: ar.imports.normalizedPhone,
      render: (row) =>
        row.normalized_phone ? (
          <span className="font-mono text-sm font-semibold text-primary" dir="ltr">
            {row.normalized_phone}
          </span>
        ) : (
          <span className="text-xs text-default-400">-</span>
        ),
    },
    {
      id: 'group_name',
      header: ar.imports.group,
      render: (row) =>
        row.group_name ? (
          <Chip size="sm" variant="flat" color="default" className="text-xs">
            {row.group_name}
          </Chip>
        ) : (
          <span className="text-xs text-default-400">-</span>
        ),
    },
    ...(selectedTab === 'invalid'
      ? [
          {
            id: 'error',
            header: ar.imports.errorReason,
            render: (row: ImportRow) => (
              <span className="text-xs text-danger font-medium">{row.error || 'رقم غير صالح'}</span>
            ),
          },
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-6 max-w-6xl mx-auto w-full">
      {/* Overview Metric Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {/* New Drivers Card */}
        <div className="flex flex-col gap-1 rounded-2xl border border-primary-200 bg-primary-50/50 p-4">
          <span className="text-xs text-primary-700 font-medium">{ar.imports.tabs.new}</span>
          <span className="text-2xl font-black text-primary-900">{counts?.new || 0}</span>
          <span className="text-[11px] text-primary-600">سيتم إضافتهم كسائقين جدد</span>
        </div>

        {/* Duplicate / Existing Card */}
        <div className="flex flex-col gap-1 rounded-2xl border border-warning-200 bg-warning-50/50 p-4">
          <span className="text-xs text-warning-700 font-medium">{ar.imports.tabs.duplicate}</span>
          <span className="text-2xl font-black text-warning-900">{duplicateTotal}</span>
          <span className="text-[11px] text-warning-600">سيتم ربط المجموعات فقط</span>
        </div>

        {/* Active Card */}
        <div className="flex flex-col gap-1 rounded-2xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-800/50 dark:bg-emerald-950/20 p-4">
          <span className="text-xs text-emerald-700 dark:text-emerald-400 font-medium">{ar.imports.tabs.active}</span>
          <span className="text-2xl font-black text-emerald-900 dark:text-emerald-300">{counts?.active || 0}</span>
          <span className="text-[11px] text-emerald-600 dark:text-emerald-400">سائقون نشطون حالياً</span>
        </div>

        {/* Invalid Card */}
        <div className="flex flex-col gap-1 rounded-2xl border border-danger-200 bg-danger-50/50 p-4">
          <span className="text-xs text-danger-700 font-medium">{ar.imports.tabs.invalid}</span>
          <span className="text-2xl font-black text-danger-900">{counts?.invalid || 0}</span>
          <span className="text-[11px] text-danger-600">سيتم استبعادهم تلقائياً</span>
        </div>
      </div>

      {/* Tabs & Table Container */}
      <div className="flex flex-col gap-4 rounded-2xl border border-default-200 bg-content1 p-4 sm:p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-default-100 pb-4">
          <div className="overflow-x-auto max-w-full pb-1">
            <Tabs
              selectedKey={selectedTab}
              onSelectionChange={handleTabChange}
              color="primary"
              variant="underlined"
              disableAnimation
              classNames={{
                tabList: 'gap-6 p-0',
                cursor: 'w-full bg-primary',
                tab: 'max-w-fit px-0 h-10',
              }}
            >
              <Tab
                key="new"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.imports.tabs.new}</span>
                    <Chip size="sm" variant="flat" color="primary">
                      {counts?.new || 0}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="duplicate"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.imports.tabs.duplicate}</span>
                    <Chip size="sm" variant="flat" color="warning">
                      {duplicateTotal}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="active"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.imports.tabs.active}</span>
                    <Chip
                      size="sm"
                      variant="flat"
                      className="bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60 font-semibold"
                    >
                      {counts?.active || 0}
                    </Chip>
                  </div>
                }
              />
              <Tab
                key="invalid"
                title={
                  <div className="flex items-center gap-2">
                    <span>{ar.imports.tabs.invalid}</span>
                    <Chip size="sm" variant="flat" color="danger">
                      {counts?.invalid || 0}
                    </Chip>
                  </div>
                }
              />
            </Tabs>
          </div>

          {/* Export Invalid Button */}
          {(counts?.invalid || 0) > 0 && (
            <Button
              as="a"
              href={importService.getExportInvalidUrl(importItem.id)}
              download={`invalid_rows_${importItem.id}.csv`}
              size="sm"
              color="danger"
              variant="flat"
              className="min-h-[44px]"
              startContent={<ArrowDownTrayIcon className="h-4 w-4" />}
            >
              {ar.imports.exportInvalid}
            </Button>
          )}
        </div>

        {/* Tab Search Bar */}
        <div className="w-full sm:w-72">
          <SearchInput
            placeholder="بحث في الأرقام..."
            value={search}
            onSearch={(val) => {
              setSearch(val);
              setDebouncedSearch(val);
              setCurrentPage(1);
            }}
            size="sm"
            variant="bordered"
            className="w-full"
          />
        </div>

        {/* Staged Rows Table */}
        <DataTable
          columns={columns}
          data={rows}
          keyExtractor={(row) => row.id}
          isLoading={isLoading}
          emptyTitle={ar.imports.noRowsInTab}
          currentPage={currentPage}
          lastPage={lastPage}
          total={total}
          onPageChange={(page) => setCurrentPage(page)}
        />
      </div>

      {/* Bottom Action Footer */}
      <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-4 rounded-2xl border border-default-200 bg-content1 p-4 shadow-sm">
        <Button
          color="danger"
          variant="light"
          className="min-h-[44px]"
          onPress={() => setIsCancelConfirmOpen(true)}
          startContent={<XCircleIcon className="h-4 w-4" />}
        >
          {ar.imports.cancelImport}
        </Button>

        <div className="flex items-center gap-3">
          <Button
            color="primary"
            size="lg"
            className="font-bold px-8 min-h-[44px] w-full sm:w-auto"
            onPress={() => setIsCommitConfirmOpen(true)}
            startContent={<CheckCircleIcon className="h-5 w-5" />}
          >
            {ar.imports.commitButton}
          </Button>
        </div>
      </div>

      {/* Cancel Confirmation Dialog */}
      <ConfirmDialog
        isOpen={isCancelConfirmOpen}
        onClose={() => setIsCancelConfirmOpen(false)}
        onConfirm={handleCancelImport}
        title={ar.imports.cancelConfirmTitle}
        message={ar.imports.cancelConfirmMessage}
        variant="danger"
        confirmText={ar.imports.cancelImport}
        isLoading={isActionLoading}
      />

      {/* Commit Confirmation Dialog */}
      <ConfirmDialog
        isOpen={isCommitConfirmOpen}
        onClose={() => setIsCommitConfirmOpen(false)}
        onConfirm={handleCommitImport}
        title={ar.imports.commitConfirmTitle}
        message={ar.imports.commitConfirmMessage}
        variant="primary"
        confirmText={ar.imports.commitButton}
        isLoading={isActionLoading}
      />
    </div>
  );
};

export default ImportPreviewStep;
