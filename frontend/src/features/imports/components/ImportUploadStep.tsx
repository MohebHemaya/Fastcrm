import React, { useState, useRef, useEffect } from 'react';
import { Button, Chip } from '@heroui/react';
import {
  ArrowUpTrayIcon,
  ArrowDownTrayIcon,
  DocumentTextIcon,
  ExclamationCircleIcon,
  CheckCircleIcon,
  TrashIcon,
  ArrowRightIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import importService from '@/services/importService';
import type { ImportItem } from '@/types/import';

interface ImportUploadStepProps {
  onFileUploaded: (item: ImportItem) => void;
  onOpenPending?: (item: ImportItem) => void;
}

export const ImportUploadStep: React.FC<ImportUploadStepProps> = ({
  onFileUploaded,
  onOpenPending,
}) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);

  // Pending imports state
  const [pendingImports, setPendingImports] = useState<ImportItem[]>([]);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const fetchPendingImports = async () => {
    try {
      const res = await importService.getImports({ status: 'ready_for_review', per_page: 5 });
      setPendingImports(res.data || []);
    } catch {
      // Ignore background fetch failure
    }
  };

  useEffect(() => {
    fetchPendingImports();
  }, []);

  const handleDeletePending = async (id: number) => {
    if (!window.confirm('هل أنت متأكد من حذف عملية الاستيراد هذه؟')) return;

    try {
      setDeletingId(id);
      await importService.deleteImport(id);
      setPendingImports((prev) => prev.filter((item) => item.id !== id));
    } catch {
      setError('تعذر حذف عملية الاستيراد.');
    } finally {
      setDeletingId(null);
    }
  };

  const handleFileChange = (file: File | undefined) => {
    setError(null);
    if (!file) return;

    const validExtensions = ['.xlsx', '.xls', '.csv'];
    const lowerName = file.name.toLowerCase();
    const hasValidExt = validExtensions.some((ext) => lowerName.endsWith(ext));

    if (!hasValidExt) {
      setError('نوع الملف غير مدعوم. يرجى اختيار ملف Excel (.xlsx, .xls) أو CSV (.csv).');
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      setError('حجم الملف يتجاوز الحد الأقصى المسموح به (10 ميجابايت).');
      return;
    }

    setSelectedFile(file);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files?.[0];
    handleFileChange(file);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleUpload = async () => {
    if (!selectedFile) return;

    setIsUploading(true);
    setError(null);

    try {
      const response = await importService.uploadImport(selectedFile);
      onFileUploaded(response.data);
    } catch (err: any) {
      const msg =
        err.response?.data?.message ||
        err.response?.data?.meta?.errors?.file?.[0] ||
        ar.errors.generalError;
      setError(msg);
      setIsUploading(false);
    }
  };

  return (
    <div className="flex flex-col gap-6 max-w-3xl mx-auto w-full">
      {/* Pending Imports Section */}
      {pendingImports.length > 0 && (
        <div className="rounded-2xl border border-warning-200 bg-warning-50/40 p-5 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="flex h-2.5 w-2.5 rounded-full bg-warning-500 animate-pulse" />
              <h4 className="text-sm font-bold text-default-900">
                عمليات استيراد سابقة جاهزة للمراجعة والاعتماد ({pendingImports.length})
              </h4>
            </div>
            <span className="text-xs text-default-500">تم فحصها مسبقاً وبانتظار اعتمادك</span>
          </div>

          <div className="divide-y divide-warning-100 rounded-xl bg-content1 border border-warning-200/60 overflow-hidden shadow-xs">
            {pendingImports.map((item) => {
              const counts = item.counts || { total: 0, new: 0, duplicate_in_file: 0, existing: 0, active: 0, invalid: 0 };
              const validCount = (counts.new || 0) + (counts.existing || 0);

              return (
                <div
                  key={item.id}
                  className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 gap-3 hover:bg-default-50/80 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary">
                      <DocumentTextIcon className="h-5 w-5" />
                    </div>
                    <div>
                      <p className="text-sm font-bold text-default-800">{item.original_name}</p>
                      <div className="flex flex-wrap items-center gap-1.5 mt-1">
                        <Chip size="sm" variant="flat" color="primary" className="text-[11px]">
                          إجمالي: {counts.total || 0}
                        </Chip>
                        <Chip size="sm" variant="flat" color="success" className="text-[11px]">
                          صالح: {validCount}
                        </Chip>
                        {(counts.invalid || 0) > 0 && (
                          <Chip size="sm" variant="flat" color="danger" className="text-[11px]">
                            غير صالح: {counts.invalid}
                          </Chip>
                        )}
                        <span className="text-[11px] text-default-400 ms-1">
                          {new Date(item.created_at).toLocaleDateString('ar-EG')}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 self-end sm:self-center">
                    <Button
                      size="sm"
                      color="danger"
                      variant="light"
                      isLoading={deletingId === item.id}
                      onPress={() => handleDeletePending(item.id)}
                      startContent={<TrashIcon className="h-4 w-4" />}
                    >
                      حذف
                    </Button>
                    {onOpenPending && (
                      <Button
                        size="sm"
                        color="primary"
                        variant="solid"
                        onPress={() => onOpenPending(item)}
                        startContent={<ArrowRightIcon className="h-4 w-4" />}
                        className="font-bold"
                      >
                        مراجعة واعتماد
                      </Button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Download Template Banner */}
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-primary-200 bg-primary-50/60 p-4">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
            <DocumentTextIcon className="h-5 w-5" />
          </div>
          <div>
            <h4 className="text-sm font-bold text-primary-900">{ar.imports.downloadTemplate}</h4>
            <p className="text-xs text-primary-700">
              ملف Excel/CSV يحتوي على الأعمدة المطلوبة (رقم الهاتف، اسم المجموعة) لتفادي أخطاء الاستيراد.
            </p>
          </div>
        </div>
        <Button
          as="a"
          href={importService.getTemplateUrl()}
          download="drivers_template.csv"
          size="sm"
          color="primary"
          variant="flat"
          startContent={<ArrowDownTrayIcon className="h-4 w-4" />}
        >
          {ar.imports.downloadTemplate}
        </Button>
      </div>

      {/* Drag & Drop Area */}
      <div
        className={`relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-8 text-center transition-colors cursor-pointer ${
          isDragging
            ? 'border-primary bg-primary-50/40'
            : selectedFile
            ? 'border-success-300 bg-success-50/30'
            : 'border-default-300 hover:border-primary-400 bg-content1'
        }`}
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onClick={() => fileInputRef.current?.click()}
      >
        <input
          ref={fileInputRef}
          type="file"
          accept=".xlsx,.xls,.csv"
          className="hidden"
          onChange={(e) => handleFileChange(e.target.files?.[0])}
        />

        <div className="flex flex-col items-center gap-3">
          <div
            className={`flex h-16 w-16 items-center justify-center rounded-2xl ${
              selectedFile ? 'bg-success-100 text-success' : 'bg-primary-100 text-primary'
            }`}
          >
            {selectedFile ? <DocumentTextIcon className="h-8 w-8" /> : <ArrowUpTrayIcon className="h-8 w-8" />}
          </div>

          <div>
            <p className="text-base font-bold text-default-800">
              {selectedFile ? selectedFile.name : ar.imports.dragDropText}
            </p>
            <p className="text-xs text-default-500 mt-1">
              {selectedFile
                ? `${(selectedFile.size / 1024).toFixed(1)} كيلوبايت — انقر لتغيير الملف`
                : ar.imports.supportedFormats}
            </p>
          </div>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <div className="flex items-center gap-2 rounded-xl bg-danger-50 p-4 text-sm text-danger border border-danger-200">
          <ExclamationCircleIcon className="h-5 w-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Action Button */}
      <div className="flex justify-end">
        <Button
          color="primary"
          size="lg"
          className="w-full sm:w-auto font-bold px-8"
          isDisabled={!selectedFile || isUploading}
          isLoading={isUploading}
          onPress={handleUpload}
          startContent={!isUploading && <CheckCircleIcon className="h-5 w-5" />}
        >
          {ar.imports.uploadButton}
        </Button>
      </div>
    </div>
  );
};

export default ImportUploadStep;
