import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Progress,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  DocumentArrowDownIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import exportService from '@/services/exportService';
import type { ExportItem } from '@/types/export';
import { useUiStore } from '@/lib/stores/useUiStore';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface ExportModalProps {
  isOpen: boolean;
  onClose: () => void;
  activeExportId?: number | null;
  onExportTriggered?: (exportItem: ExportItem) => void;
}

export const ExportModal: React.FC<ExportModalProps> = ({
  isOpen,
  onClose,
  activeExportId,
}) => {
  const isMobile = useIsMobile();
  const addToast = useUiStore((state) => state.addToast);
  const [exportsList, setExportsList] = useState<ExportItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [retryingId, setRetryingId] = useState<number | null>(null);
  const pollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchExports = useCallback(async () => {
    try {
      const res = await exportService.getExports();
      setExportsList(res.data || []);
      return res.data || [];
    } catch (e: any) {
      console.error('Failed to load exports', e);
      return [];
    }
  }, []);

  useEffect(() => {
    if (isOpen) {
      setIsLoading(true);
      fetchExports().finally(() => setIsLoading(false));
    } else {
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
        pollTimerRef.current = null;
      }
    }
  }, [isOpen, fetchExports]);

  // Polling effect when there are active jobs (queued or processing)
  useEffect(() => {
    if (!isOpen) return;

    const hasPending = exportsList.some(
      (item) => item.status === 'queued' || item.status === 'processing'
    );

    if (hasPending) {
      if (!pollTimerRef.current) {
        pollTimerRef.current = setInterval(async () => {
          const updated = await fetchExports();
          const stillPending = updated.some(
            (item) => item.status === 'queued' || item.status === 'processing'
          );
          if (!stillPending && pollTimerRef.current) {
            clearInterval(pollTimerRef.current);
            pollTimerRef.current = null;
          }
        }, 2000);
      }
    } else {
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
        pollTimerRef.current = null;
      }
    }

    return () => {
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
        pollTimerRef.current = null;
      }
    };
  }, [isOpen, exportsList, fetchExports]);

  const handleDownload = async (item: ExportItem) => {
    setDownloadingId(item.id);
    try {
      const filename = `${item.type}_export_${item.id}.xlsx`;
      await exportService.downloadExport(item.id, filename);
      addToast({
        type: 'success',
        message: ar.exports.statusDone,
      });
    } catch (e: any) {
      console.error('Download failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setDownloadingId(null);
    }
  };

  const handleRetry = async (item: ExportItem) => {
    setRetryingId(item.id);
    try {
      await exportService.requestExport({
        type: item.type,
        params: item.params,
      });
      addToast({
        type: 'info',
        message: ar.exports.exportStarted,
      });
      await fetchExports();
    } catch (e: any) {
      console.error('Retry failed', e);
      addToast({
        type: 'error',
        message: e?.response?.data?.message || ar.errors.generalError,
      });
    } finally {
      setRetryingId(null);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'queued':
        return (
          <Chip size="sm" color="warning" variant="flat" startContent={<ClockIcon className="h-3 w-3" />}>
            {ar.exports.statusQueued}
          </Chip>
        );
      case 'processing':
        return (
          <Chip size="sm" color="primary" variant="flat" startContent={<Spinner size="sm" color="current" />}>
            {ar.exports.statusProcessing}
          </Chip>
        );
      case 'done':
        return (
          <Chip size="sm" color="success" variant="flat" startContent={<CheckCircleIcon className="h-3 w-3" />}>
            {ar.exports.statusDone}
          </Chip>
        );
      case 'failed':
      default:
        return (
          <Chip size="sm" color="danger" variant="flat" startContent={<ExclamationTriangleIcon className="h-3 w-3" />}>
            {ar.exports.statusFailed}
          </Chip>
        );
    }
  };

  const getTypeName = (type: string) => {
    switch (type) {
      case 'drivers':
        return ar.exports.exportDrivers;
      case 'work_order':
        return ar.exports.exportWorkOrders;
      case 'group_performance':
        return ar.exports.exportGroupPerformance;
      default:
        return type;
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : '2xl'}
      scrollBehavior="inside"
      aria-label={ar.exports.modalTitle}
      classNames={{
        base: isMobile ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none' : '',
      }}
    >
      <ModalContent>
        <ModalHeader className="flex items-center justify-between gap-2 border-b border-default-200">
          <div className="flex items-center gap-2">
            <DocumentArrowDownIcon className="h-5 w-5 text-primary" />
            <span className="text-base font-bold text-default-900">{ar.exports.modalTitle}</span>
          </div>
          <Button
            size="sm"
            variant="light"
            isIconOnly
            onPress={fetchExports}
            title={ar.common.refresh}
          >
            <ArrowPathIcon className="h-4 w-4" />
          </Button>
        </ModalHeader>

        <ModalBody className="py-4 space-y-4">
          {isLoading && exportsList.length === 0 ? (
            <div className="flex justify-center items-center py-12">
              <Spinner size="lg" label={ar.app.loading} />
            </div>
          ) : exportsList.length === 0 ? (
            <div className="text-center py-10 text-default-400">
              <DocumentArrowDownIcon className="h-10 w-10 mx-auto mb-2 opacity-50" />
              <p className="text-sm">{ar.exports.noExports}</p>
            </div>
          ) : (
            <div className="space-y-3">
              {exportsList.map((item) => {
                const isPending = item.status === 'queued' || item.status === 'processing';
                const isFailed = item.status === 'failed';
                const isDone = item.status === 'done';

                return (
                  <div
                    key={item.id}
                    className={`p-4 rounded-xl border transition-all ${
                      item.id === activeExportId
                        ? 'border-primary/50 bg-primary-50/20'
                        : 'border-default-200 bg-content1'
                    }`}
                  >
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      <div className="flex items-start gap-3">
                        <div className="p-2 rounded-lg bg-default-100 text-default-700 mt-0.5">
                          <DocumentArrowDownIcon className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-sm text-default-900">
                              {getTypeName(item.type)}
                            </span>
                            {getStatusBadge(item.status)}
                          </div>
                          <div className="text-xs text-default-400 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            <span>
                              {new Date(item.created_at).toLocaleString('ar-EG', {
                                dateStyle: 'short',
                                timeStyle: 'short',
                              })}
                            </span>
                            {item.expires_at && (
                              <span>
                                {ar.exports.expiresAt}:{' '}
                                {new Date(item.expires_at).toLocaleDateString('ar-EG')}
                              </span>
                            )}
                          </div>
                          {item.error && (
                            <p className="text-xs text-danger mt-1.5 font-medium">{item.error}</p>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-2 self-end sm:self-center">
                        {isDone && (
                          <Button
                            size="sm"
                            color="success"
                            variant="flat"
                            startContent={<ArrowDownTrayIcon className="h-4 w-4" />}
                            isLoading={downloadingId === item.id}
                            onPress={() => handleDownload(item)}
                          >
                            {ar.exports.download}
                          </Button>
                        )}
                        {isFailed && (
                          <Button
                            size="sm"
                            color="danger"
                            variant="flat"
                            startContent={<ArrowPathIcon className="h-4 w-4" />}
                            isLoading={retryingId === item.id}
                            onPress={() => handleRetry(item)}
                          >
                            {ar.exports.retry}
                          </Button>
                        )}
                      </div>
                    </div>

                    {isPending && (
                      <div className="mt-3">
                        <Progress
                          size="sm"
                          isIndeterminate
                          color={item.status === 'processing' ? 'primary' : 'warning'}
                          aria-label={item.status}
                          className="max-w-full"
                        />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </ModalBody>

        <ModalFooter className="border-t border-default-200 safe-bottom">
          <Button variant="light" className="w-full sm:w-auto min-h-[44px]" onPress={onClose}>
            {ar.exports.close}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default ExportModal;
