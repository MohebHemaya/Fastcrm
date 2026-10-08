import React, { useTransition } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@heroui/react';
import {
  CheckCircleIcon,
  UsersIcon,
  ShieldCheckIcon,
  DocumentDuplicateIcon,
  ExclamationTriangleIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  ArrowRightIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import importService from '@/services/importService';
import type { ImportItem } from '@/types/import';

interface ImportReportStepProps {
  importItem: ImportItem;
  onReset: () => void;
}

export const ImportReportStep: React.FC<ImportReportStepProps> = ({
  importItem,
  onReset,
}) => {
  const navigate = useNavigate();
  const [, startTransition] = useTransition();

  const counts = importItem.counts;
  const existingUpdated = (counts?.existing || 0) + (counts?.active || 0);

  return (
    <div className="flex flex-col items-center gap-8 max-w-2xl mx-auto py-6 w-full text-center">
      {/* Success Animation & Header */}
      <div className="flex flex-col items-center gap-3">
        <div className="flex h-20 w-20 items-center justify-center rounded-3xl bg-success-100 text-success shadow-lg shadow-success-100/50">
          <CheckCircleIcon className="h-10 w-10" />
        </div>
        <h2 className="text-2xl font-black text-default-900">{ar.imports.reportTitle}</h2>
        <p className="text-sm text-default-500 max-w-md">
          تم تحديث قاعدة البيانات بنجاح واستيراد السائقين وربط المجموعات مع الحفاظ على سلامة الحالات السابقة.
        </p>
      </div>

      {/* Summary Metrics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full text-start">
        {/* New Drivers Card */}
        <div className="flex items-center gap-4 rounded-2xl border border-primary-200 bg-primary-50/50 p-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
            <UsersIcon className="h-6 w-6" />
          </div>
          <div>
            <span className="block text-2xl font-black text-primary-900">{counts?.new || 0}</span>
            <span className="text-xs text-primary-700 font-medium">{ar.imports.newDriversAdded}</span>
          </div>
        </div>

        {/* Existing Updated Card */}
        <div className="flex items-center gap-4 rounded-2xl border border-success-200 bg-success-50/50 p-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-success text-white">
            <ShieldCheckIcon className="h-6 w-6" />
          </div>
          <div>
            <span className="block text-2xl font-black text-success-900">{existingUpdated}</span>
            <span className="text-xs text-success-700 font-medium">{ar.imports.existingUpdated}</span>
          </div>
        </div>

        {/* Duplicates Merged Card */}
        <div className="flex items-center gap-4 rounded-2xl border border-warning-200 bg-warning-50/50 p-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-warning text-white">
            <DocumentDuplicateIcon className="h-6 w-6" />
          </div>
          <div>
            <span className="block text-2xl font-black text-warning-900">
              {counts?.duplicate_in_file || 0}
            </span>
            <span className="text-xs text-warning-700 font-medium">
              {ar.imports.duplicatesIgnored}
            </span>
          </div>
        </div>

        {/* Invalid Excluded Card */}
        <div className="flex items-center gap-4 rounded-2xl border border-danger-200 bg-danger-50/50 p-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-danger text-white">
            <ExclamationTriangleIcon className="h-6 w-6" />
          </div>
          <div>
            <span className="block text-2xl font-black text-danger-900">{counts?.invalid || 0}</span>
            <span className="text-xs text-danger-700 font-medium">{ar.imports.invalidExcluded}</span>
          </div>
        </div>
      </div>

      {/* Download Invalid Numbers Button if any */}
      {(counts?.invalid || 0) > 0 && (
        <div className="w-full flex items-center justify-between rounded-xl border border-danger-200 bg-danger-50/40 p-4 text-start">
          <div className="text-xs text-danger-800">
            <span className="font-bold">يوجد {counts.invalid} رقم غير صالح:</span> يمكنك تنزيل ملف CSV يوضح تفاصيل وأسباب عدم قبول الأرقام.
          </div>
          <Button
            as="a"
            href={importService.getExportInvalidUrl(importItem.id)}
            download={`invalid_rows_${importItem.id}.csv`}
            size="sm"
            color="danger"
            variant="flat"
            startContent={<ArrowDownTrayIcon className="h-4 w-4" />}
          >
            {ar.imports.exportInvalid}
          </Button>
        </div>
      )}

      {/* Navigation Actions */}
      <div className="flex flex-wrap items-center justify-center gap-4 pt-4">
        <Button
          color="primary"
          size="lg"
          className="font-bold px-8"
          onPress={() => {
            startTransition(() => {
              navigate('/drivers');
            });
          }}
          startContent={<ArrowRightIcon className="h-5 w-5 rtl:rotate-180" />}
        >
          {ar.imports.goToDrivers}
        </Button>

        <Button
          variant="flat"
          color="default"
          size="lg"
          onPress={onReset}
          startContent={<ArrowPathIcon className="h-4 w-4" />}
        >
          {ar.imports.startNewImport}
        </Button>
      </div>
    </div>
  );
};

export default ImportReportStep;
