import { describe, it, expect } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import ImportWizardPage from '@/routes/ImportWizardPage';
import { ar } from '@/i18n/ar';

const renderWizard = () => {
  return render(
    <HeroUIProvider locale="ar-EG">
      <MemoryRouter>
        <ImportWizardPage />
      </MemoryRouter>
    </HeroUIProvider>
  );
};

describe('ImportWizardPage', () => {
  it('renders upload step initially with download template link', () => {
    renderWizard();

    expect(screen.getByRole('heading', { level: 1, name: ar.imports.wizardTitle })).toBeInTheDocument();
    expect(screen.getByText(ar.imports.dragDropText)).toBeInTheDocument();
    expect(screen.getAllByText(ar.imports.downloadTemplate).length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: new RegExp(ar.imports.uploadButton) })).toBeDisabled();
  });

  it('selects file, enables upload button, and uploads', async () => {
    const user = userEvent.setup();
    renderWizard();

    const file = new File(['phone,group\n01012345678,Cairo'], 'drivers.xlsx', {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(fileInput).toBeInTheDocument();

    await user.upload(fileInput, file);

    const uploadButton = screen.getByRole('button', { name: new RegExp(ar.imports.uploadButton) });
    expect(uploadButton).toBeEnabled();

    await user.click(uploadButton);

    // After upload, it progresses to parsing and then preview step
    await waitFor(() => {
      // In preview step, it displays the 4 tabs and metrics
      expect(screen.getAllByText(ar.imports.tabs.new).length).toBeGreaterThan(0);
      expect(screen.getAllByText(ar.imports.tabs.duplicate).length).toBeGreaterThan(0);
      expect(screen.getAllByText(ar.imports.tabs.active).length).toBeGreaterThan(0);
      expect(screen.getAllByText(ar.imports.tabs.invalid).length).toBeGreaterThan(0);
    }, { timeout: 4000 });
  });

  it('allows committing from preview and navigates to report step', async () => {
    const user = userEvent.setup();
    renderWizard();

    const file = new File(['phone,group\n01012345678,Cairo'], 'drivers.xlsx', {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    await user.upload(fileInput, file);

    const uploadButton = screen.getByRole('button', { name: new RegExp(ar.imports.uploadButton) });
    await user.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: new RegExp(ar.imports.commitButton) })).toBeInTheDocument();
    }, { timeout: 4000 });

    const commitButton = screen.getByRole('button', { name: new RegExp(ar.imports.commitButton) });
    await user.click(commitButton);

    // Confirm dialog opens
    await waitFor(() => {
      expect(screen.getByText(ar.imports.commitConfirmTitle)).toBeInTheDocument();
    });

    const confirmModalButton = screen.getAllByRole('button', { name: new RegExp(ar.imports.commitButton) });
    // Click the confirm button in the modal
    await user.click(confirmModalButton[confirmModalButton.length - 1]);

    // Transitions to report step
    await waitFor(() => {
      expect(screen.getByText(ar.imports.reportTitle)).toBeInTheDocument();
      expect(screen.getByText(ar.imports.goToDrivers)).toBeInTheDocument();
      expect(screen.getByText(ar.imports.startNewImport)).toBeInTheDocument();
    }, { timeout: 4000 });
  });
});
