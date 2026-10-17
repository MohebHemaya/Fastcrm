import React, { useEffect, useState, useRef } from 'react';
import { Spinner, Progress, Button } from '@heroui/react';
import { ExclamationCircleIcon, ArrowPathIcon } from '@/components/icons';
import { ar } from '@/i18n/ar';
import importService from '@/services/importService';
import type { ImportItem } from '@/types/import';

interface ImportParsingStepProps {
  importId: number;
  onParsingComplete: (item: ImportItem) => void;
  onErrorReset: () => void;
}

export const ImportParsingStep: React.FC<ImportParsingStepProps> = ({
  importId,
  onParsingComplete,
  onErrorReset,
}) => {
  const [importItem, setImportItem] = useState<ImportItem | null>(null);
  const [error, setError] = useState<string | null>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const pollStatus = async () => {
    try {
      const res = await importService.getImport(importId);
      const data = res.data;
      setImportItem(data);

      const statusValue =
        typeof data.status === 'object' && data.status !== null
          ? (data.status as { value: string }).value
          : data.status;

      if (statusValue === 'ready_for_review') {
        if (timerRef.current) clearInterval(timerRef.current);
        onParsingComplete(data);
      } else if (statusValue === 'failed') {
        if (timerRef.current) clearInterval(timerRef.current);
        setError(data.error_message || (data as any).error || 'فشلت معالجة الملف، يرجى التأكد من صحة التنسيق والمحاولة ثانية.');
      }
    } catch {
      setError(ar.errors.networkError);
    }
  };

  useEffect(() => {
    pollStatus();
    timerRef.current = setInterval(pollStatus, 3000);

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [importId]);

  const progressPercent =
    importItem && importItem.total_chunks > 0
      ? Math.round((importItem.processed_chunks / importItem.total_chunks) * 100)
      : undefined;

  return (
    <div className="flex flex-col items-center justify-center gap-6 max-w-lg mx-auto py-12 text-center w-full">
      {error ? (
        <div className="flex flex-col items-center gap-4 w-full">
          <ExclamationCircleIcon className="h-6 w-6 text-danger" />
          <div>
            <h3 className="text-lg font-bold text-danger">تعذر معالجة الملف</h3>
            <p className="text-sm text-default-600 mt-1 max-w-sm">{error}</p>
          </div>
          <Button
            color="primary"
            variant="flat"
            startContent={<ArrowPathIcon className="h-4 w-4" />}
            onPress={onErrorReset}
          >
            {ar.imports.startNewImport}
          </Button>
        </div>
      ) : (
        <div className="flex flex-col items-center gap-6 w-full">
          <div className="relative flex items-center justify-center">
            <Spinner size="lg" color="primary" />
          </div>

          <div className="flex flex-col gap-2">
            <h3 className="text-lg font-bold text-default-900">{ar.imports.stepParsing}</h3>
            <p className="text-sm text-default-500 max-w-md leading-relaxed">
              {ar.imports.parsingNotice}
            </p>
          </div>

          {progressPercent !== undefined && progressPercent > 0 && (
            <div className="w-full max-w-xs flex flex-col gap-2">
              <Progress
                value={progressPercent}
                color="primary"
                showValueLabel
                className="w-full"
              />
              <span className="text-xs text-default-400">
                {ar.imports.processedChunks}: {importItem?.processed_chunks} / {importItem?.total_chunks}
              </span>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default ImportParsingStep;
