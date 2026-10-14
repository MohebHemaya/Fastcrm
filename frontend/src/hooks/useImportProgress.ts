import { useEffect, useState, useRef } from 'react';
import echo from '@/lib/echo';

export interface ImportProgressState {
  jobId: string | null;
  processed: number;
  total: number;
  percent: number;
  completed: boolean;
  status: 'idle' | 'processing' | 'completed' | 'failed';
  error: string | null;
}

export function useImportProgress(jobId: string | number | null, onCompleted?: () => void) {
  const [progress, setProgress] = useState<ImportProgressState>({
    jobId: jobId ? String(jobId) : null,
    processed: 0,
    total: 0,
    percent: 0,
    completed: false,
    status: 'idle',
    error: null,
  });

  const onCompletedRef = useRef(onCompleted);
  useEffect(() => {
    onCompletedRef.current = onCompleted;
  }, [onCompleted]);

  useEffect(() => {
    if (!jobId) return;

    const channelName = `import.${jobId}`;
    const channel = echo.private(channelName);

    channel.listen('.ImportProgressUpdated', (payload: any) => {
      setProgress({
        jobId: String(payload.job_id),
        processed: payload.processed ?? 0,
        total: payload.total ?? 0,
        percent: payload.percent ?? 0,
        completed: Boolean(payload.completed),
        status: payload.status ?? 'processing',
        error: payload.error ?? null,
      });

      if (payload.completed && payload.status === 'completed') {
        onCompletedRef.current?.();
      }
    });

    return () => {
      echo.leave(channelName);
    };
  }, [jobId]);

  return progress;
}

export default useImportProgress;
