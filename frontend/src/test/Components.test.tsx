import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import PageHeader from '@/components/ui/PageHeader';
import EmptyState from '@/components/ui/EmptyState';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';

describe('Reusable UI Components', () => {
  describe('PageHeader', () => {
    it('renders title, description, and breadcrumbs correctly', () => {
      render(
        <MemoryRouter>
          <PageHeader
            title="عنوان الصفحة التجريبي"
            description="وصف تفصيلي للصفحة"
            breadcrumbs={[
              { label: 'الرئيسية', href: '/' },
              { label: 'سجل النشاط' },
            ]}
            actions={<button>إجراء إضافي</button>}
          />
        </MemoryRouter>
      );

      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('عنوان الصفحة التجريبي');
      expect(screen.getByText('وصف تفصيلي للصفحة')).toBeInTheDocument();
      expect(screen.getByText('الرئيسية')).toBeInTheDocument();
      expect(screen.getByText('سجل النشاط')).toBeInTheDocument();
      expect(screen.getByText('إجراء إضافي')).toBeInTheDocument();
    });
  });

  describe('EmptyState', () => {
    it('renders title, description, and action button', () => {
      render(
        <EmptyState
          title="لا توجد بيانات حالياً"
          description="يرجى إضافة عناصر جديدة للبدء"
          action={<button>إضافة عنصر</button>}
        />
      );

      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('لا توجد بيانات حالياً')).toBeInTheDocument();
      expect(screen.getByText('يرجى إضافة عناصر جديدة للبدء')).toBeInTheDocument();
      expect(screen.getByText('إضافة عنصر')).toBeInTheDocument();
    });
  });

  describe('ConfirmDialog', () => {
    it('renders when open and triggers onConfirm and onClose', async () => {
      const user = userEvent.setup();
      const onConfirm = vi.fn();
      const onClose = vi.fn();

      render(
        <ConfirmDialog
          isOpen={true}
          onClose={onClose}
          onConfirm={onConfirm}
          title="تأكيد الحذف"
          message="هل أنت متأكد من الحذف؟"
          confirmText="نعم، حذف"
          cancelText="تراجع"
          variant="danger"
        />
      );

      expect(screen.getByText('تأكيد الحذف')).toBeInTheDocument();
      expect(screen.getByText('هل أنت متأكد من الحذف؟')).toBeInTheDocument();

      await user.click(screen.getByRole('button', { name: 'نعم، حذف' }));
      expect(onConfirm).toHaveBeenCalledTimes(1);

      await user.click(screen.getByRole('button', { name: 'تراجع' }));
      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  describe('DataTable', () => {
    interface TestItem {
      id: number;
      name: string;
      role: string;
    }

    const testColumns: ColumnDef<TestItem>[] = [
      { id: 'name', header: 'الاسم', accessor: 'name', sortable: true },
      { id: 'role', header: 'الدور', accessor: 'role' },
    ];

    const testData: TestItem[] = [
      { id: 1, name: 'محمد علي', role: 'مدير' },
      { id: 2, name: 'خالد أحمد', role: 'مشرف' },
    ];

    it('renders table headers and rows accurately', () => {
      render(
        <DataTable
          columns={testColumns}
          data={testData}
          keyExtractor={(item) => item.id}
        />
      );

      expect(screen.getByText('الاسم')).toBeInTheDocument();
      expect(screen.getByText('الدور')).toBeInTheDocument();
      expect(screen.getByText('محمد علي')).toBeInTheDocument();
      expect(screen.getByText('خالد أحمد')).toBeInTheDocument();
    });

    it('displays empty state when data array is empty', () => {
      render(
        <DataTable
          columns={testColumns}
          data={[]}
          keyExtractor={(item) => item.id}
          emptyTitle="لا توجد نتائج"
        />
      );

      expect(screen.getByText('لا توجد نتائج')).toBeInTheDocument();
    });

    it('handles sorting click when column is sortable', async () => {
      const user = userEvent.setup();
      const onSort = vi.fn();

      render(
        <DataTable
          columns={testColumns}
          data={testData}
          keyExtractor={(item) => item.id}
          onSort={onSort}
        />
      );

      await user.click(screen.getByText('الاسم'));
      expect(onSort).toHaveBeenCalledWith('name');
    });

    it('handles pagination navigation', async () => {
      const user = userEvent.setup();
      const onPageChange = vi.fn();

      render(
        <DataTable
          columns={testColumns}
          data={testData}
          keyExtractor={(item) => item.id}
          currentPage={2}
          lastPage={5}
          total={10}
          onPageChange={onPageChange}
        />
      );

      expect(screen.getByText('السابق')).toBeInTheDocument();
      expect(screen.getByText('التالي')).toBeInTheDocument();

      await user.click(screen.getByText('التالي'));
      expect(onPageChange).toHaveBeenCalledWith(3);

      await user.click(screen.getByText('السابق'));
      expect(onPageChange).toHaveBeenCalledWith(1);
    });
  });
});
