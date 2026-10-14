import React, { useEffect, useRef, useState } from 'react';
import { Spinner, Button, Progress } from '@heroui/react';
import { ExclamationCircleIcon, ArrowPathIcon } from '@/components/icons';
import { ar } from '@/i18n/ar';
import importService from '@/services/importService';
import type { ImportItem } from '@/types/import';
import { useImportProgress } from '@/hooks/useImportProgress';

interface ImportCommittingStepProps {
  importId: number;
  onCommitComplete: (item: ImportItem) => void;
  onErrorReset: () => void;
}

export const ImportCommittingStep: React.FC<ImportCommittingStepProps> = ({
  importId,
  onCommitComplete,
  onErrorReset,
}) => {
  const [error, setError] = useState<string | null>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const pollStatus = async () => {
    try {
      const res = await importService.getImport(importId);
      const data = res.data;

      const statusValue =
        typeof data.status === 'object' && data.status !== null
          ? (data.status as { value: string }).value
          : data.status;

      if (statusValue === 'completed') {
        if (timerRef.current) clearInterval(timerRef.current);
        onCommitComplete(data);
      } else if (statusValue === 'failed') {
        if (timerRef.current) clearInterval(timerRef.current);
        setError(data.error_message || (data as any).error || 'حدث خطأ أثناء حفظ البيانات، يرجى مراجعة الدعم الفني.');
      }
    } catch {
      setError(ar.errors.networkError);
    }
  };

  // Real-time Pusher progress
  const liveProgress = useImportProgress(importId, () => {
    pollStatus();
  });

  useEffect(() => {
    pollStatus();
    // Relaxed polling when Pusher is tracking
    const pollInterval = liveProgress.status === 'processing' ? 6000 : 2500;
    timerRef.current = setInterval(pollStatus, pollInterval);

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [importId, liveProgress.status]);

  return (
    <div className="flex flex-col items-center justify-center gap-6 max-w-lg mx-auto py-12 text-center w-full">
      {error ? (
        <div className="flex flex-col items-center gap-4 w-full">
          <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-danger-100 text-danger">
            <ExclamationCircleIcon className="h-8 w-8" />
          </div>
          <div>
            <h3 className="text-lg font-bold text-danger">تعذر إتمام الاستيراد</h3>
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
            <Spinner size="lg" color="success" />
          </div>

          <div className="flex flex-col gap-2">
            <h3 className="text-lg font-bold text-default-900">{ar.imports.stepCommitting}</h3>
            <p className="text-sm text-default-500 max-w-md leading-relaxed">
              {ar.imports.committingNotice}
            </p>
          </div>

          {liveProgress.total > 0 && (
            <div className="w-full max-w-md pt-2">
              <Progress
                size="md"
                radius="full"
                color="success"
                label={`تم حفظ ${liveProgress.processed} من إجمالي ${liveProgress.total} صف`}
                value={liveProgress.percent}
                showValueLabel={true}
                className="w-full text-xs"
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default ImportCommittingStep;
