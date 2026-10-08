import React, { useState } from 'react';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import { ar } from '@/i18n/ar';
import type { ImportItem } from '@/types/import';
import ImportUploadStep from '@/features/imports/components/ImportUploadStep';
import ImportParsingStep from '@/features/imports/components/ImportParsingStep';
import ImportPreviewStep from '@/features/imports/components/ImportPreviewStep';
import ImportCommittingStep from '@/features/imports/components/ImportCommittingStep';
import ImportReportStep from '@/features/imports/components/ImportReportStep';

export type WizardStep = 'upload' | 'parsing' | 'preview' | 'committing' | 'report';

export const ImportWizardPage: React.FC = () => {
  const [currentStep, setCurrentStep] = useState<WizardStep>('upload');
  const [importId, setImportId] = useState<number | null>(null);
  const [importItem, setImportItem] = useState<ImportItem | null>(null);

  const steps: { key: WizardStep; label: string; number: number }[] = [
    { key: 'upload', label: ar.imports.stepUpload, number: 1 },
    { key: 'parsing', label: ar.imports.stepParsing, number: 2 },
    { key: 'preview', label: ar.imports.stepPreview, number: 3 },
    { key: 'committing', label: ar.imports.stepCommitting, number: 4 },
    { key: 'report', label: ar.imports.stepReport, number: 5 },
  ];

  const handleFileUploaded = (item: ImportItem) => {
    setImportId(item.id);
    setImportItem(item);
    if (item.status === 'ready_for_review') {
      setCurrentStep('preview');
    } else {
      setCurrentStep('parsing');
    }
  };

  const handleOpenPending = (item: ImportItem) => {
    setImportId(item.id);
    setImportItem(item);
    setCurrentStep('preview');
  };

  const handleParsingComplete = (item: ImportItem) => {
    setImportItem(item);
    setCurrentStep('preview');
  };

  const handleCommitStarted = (item: ImportItem) => {
    setImportItem(item);
    if (item.status === 'completed') {
      setCurrentStep('report');
    } else {
      setCurrentStep('committing');
    }
  };

  const handleCommitComplete = (item: ImportItem) => {
    setImportItem(item);
    setCurrentStep('report');
  };

  const handleCancelOrReset = () => {
    setImportId(null);
    setImportItem(null);
    setCurrentStep('upload');
  };

  const currentStepIndex = steps.findIndex((s) => s.key === currentStep);

  return (
    <PageContainer maxWidth="max-w-7xl" className="space-y-6">
      <PageHeader
        title={ar.imports.wizardTitle}
        description={ar.imports.wizardSubtitle}
      />

      {/* Stepper Progress Header */}
      <div className="rounded-2xl border border-default-200 bg-content1 p-4 shadow-sm">
        {/* Mobile Stepper (< sm) */}
        <div className="flex sm:hidden flex-col gap-2">
          <div className="flex items-center justify-between text-xs">
            <span className="font-bold text-primary">
              الخطوة {currentStepIndex + 1} من {steps.length}: {steps[currentStepIndex]?.label}
            </span>
            <span className="text-default-400 font-medium">
              {Math.round(((currentStepIndex + 1) / steps.length) * 100)}%
            </span>
          </div>
          <div className="h-1.5 w-full bg-default-100 rounded-full overflow-hidden">
            <div
              className="h-full bg-primary transition-all duration-300 rounded-full"
              style={{ width: `${((currentStepIndex + 1) / steps.length) * 100}%` }}
            />
          </div>
        </div>

        {/* Desktop/Tablet Stepper (>= sm) */}
        <div className="hidden sm:flex items-center justify-between overflow-x-auto gap-2">
          {steps.map((step, idx) => {
            const isDone = idx < currentStepIndex;
            const isCurrent = idx === currentStepIndex;

            return (
              <React.Fragment key={step.key}>
                <div className="flex items-center gap-3 shrink-0">
                  <div
                    className={`flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold transition-colors ${
                      isDone
                        ? 'bg-success text-white'
                        : isCurrent
                        ? 'bg-primary text-white shadow-md shadow-primary/30'
                        : 'bg-default-100 text-default-400'
                    }`}
                  >
                    {step.number}
                  </div>
                  <span
                    className={`text-xs sm:text-sm font-medium ${
                      isCurrent
                        ? 'text-primary font-bold'
                        : isDone
                        ? 'text-default-700'
                        : 'text-default-400'
                    }`}
                  >
                    {step.label}
                  </span>
                </div>

                {idx < steps.length - 1 && (
                  <div
                    className={`h-0.5 flex-1 mx-3 rounded-full transition-colors ${
                      idx < currentStepIndex ? 'bg-success' : 'bg-default-200'
                    }`}
                  />
                )}
              </React.Fragment>
            );
          })}
        </div>
      </div>

      {/* Step Components */}
      <div className="mt-2">
        {currentStep === 'upload' && (
          <ImportUploadStep
            onFileUploaded={handleFileUploaded}
            onOpenPending={handleOpenPending}
          />
        )}

        {currentStep === 'parsing' && importId && (
          <ImportParsingStep
            importId={importId}
            onParsingComplete={handleParsingComplete}
            onErrorReset={handleCancelOrReset}
          />
        )}

        {currentStep === 'preview' && importItem && (
          <ImportPreviewStep
            importItem={importItem}
            onCommitStarted={handleCommitStarted}
            onCancel={handleCancelOrReset}
          />
        )}

        {currentStep === 'committing' && importId && (
          <ImportCommittingStep
            importId={importId}
            onCommitComplete={handleCommitComplete}
            onErrorReset={handleCancelOrReset}
          />
        )}

        {currentStep === 'report' && importItem && (
          <ImportReportStep
            importItem={importItem}
            onReset={handleCancelOrReset}
          />
        )}
      </div>
    </PageContainer>
  );
};

export default ImportWizardPage;
