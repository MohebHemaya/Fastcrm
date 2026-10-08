import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import ExportModal from '@/components/exports/ExportModal';
import { ar } from '@/i18n/ar';
import exportService from '@/services/exportService';

const renderExportModal = (props: {
  isOpen: boolean;
  onClose: () => void;
  activeExportId?: number | null;
}) => {
  return render(
    <HeroUIProvider locale="ar-EG">
      <ExportModal {...props} />
    </HeroUIProvider>
  );
};

describe('ExportModal', () => {
  it('renders modal with recent exports list and status chips', async () => {
    const handleClose = vi.fn();
    renderExportModal({ isOpen: true, onClose: handleClose });

    await waitFor(() => {
      expect(screen.getByText(ar.exports.modalTitle)).toBeInTheDocument();
      expect(screen.getByText(ar.exports.exportDrivers)).toBeInTheDocument();
      expect(screen.getByText(ar.exports.statusDone)).toBeInTheDocument();
      expect(screen.getByText(ar.exports.exportGroupPerformance)).toBeInTheDocument();
      expect(screen.getByText(ar.exports.statusQueued)).toBeInTheDocument();
    });
  });

  it('triggers download when clicking download button on completed export', async () => {
    const downloadSpy = vi.spyOn(exportService, 'downloadExport').mockResolvedValue();
    const handleClose = vi.fn();
    const user = userEvent.setup();

    renderExportModal({ isOpen: true, onClose: handleClose });

    await waitFor(() => {
      expect(screen.getByText(ar.exports.statusDone)).toBeInTheDocument();
    });

    const downloadButton = screen.getByRole('button', { name: new RegExp(ar.exports.download) });
    await user.click(downloadButton);

    expect(downloadSpy).toHaveBeenCalledWith(101, 'drivers_export_101.xlsx');
    downloadSpy.mockRestore();
  });

  it('closes modal when clicking close button', async () => {
    const handleClose = vi.fn();
    const user = userEvent.setup();

    renderExportModal({ isOpen: true, onClose: handleClose });

    const closeBtn = screen.getByRole('button', { name: ar.exports.close });
    await user.click(closeBtn);

    expect(handleClose).toHaveBeenCalled();
  });
});
