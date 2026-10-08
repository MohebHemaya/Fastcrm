import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import SettingsPage from '@/features/settings/SettingsPage';
import QuestionPreview from '@/features/settings/QuestionPreview';

const renderComponent = () =>
  render(
    <BrowserRouter>
      <SettingsPage />
    </BrowserRouter>
  );

describe('SettingsPage & Questionnaire UI', () => {
  it('renders page header and question tabs', async () => {
    renderComponent();

    expect(screen.getByText('الإعدادات والأسئلة')).toBeInTheDocument();
    expect(screen.getByText(/الأسئلة والاستبيان/)).toBeInTheDocument();
    expect(screen.getByText('إعدادات النظام العامة')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText('ما نوع مركبتك؟')).toBeInTheDocument();
      expect(screen.getByText('هل السيارة مجهزة بصندوق تبريد؟')).toBeInTheDocument();
    });
  });

  it('renders live WhatsApp preview with questions and options', () => {
    render(
      <QuestionPreview
        textAr="ما نوع سيارتك؟"
        type="single_choice"
        options={[
          { value: 'pickup', label_ar: 'ربع نقل' },
          { value: 'jumbo', label_ar: 'جامبو' },
        ]}
      />
    );

    expect(screen.getByText('معاينة حية كما تظهر للسائق على واتساب')).toBeInTheDocument();
    expect(screen.getByText('ما نوع سيارتك؟')).toBeInTheDocument();
    expect(screen.getByText('ربع نقل')).toBeInTheDocument();
    expect(screen.getByText('جامبو')).toBeInTheDocument();
  });

  it('renders WhatsApp preview for yes_no type with two buttons', () => {
    render(
      <QuestionPreview
        textAr="هل السيارة مكيفة؟"
        type="yes_no"
        options={[]}
      />
    );

    expect(screen.getByText('هل السيارة مكيفة؟')).toBeInTheDocument();
    expect(screen.getByText('نعم')).toBeInTheDocument();
    expect(screen.getByText('لا')).toBeInTheDocument();
  });

  it('switches to general settings tab and displays timeout and reminder inputs', async () => {
    renderComponent();

    const generalTab = screen.getByText('إعدادات النظام العامة');
    fireEvent.click(generalTab);

    await waitFor(() => {
      expect(screen.getByText(/مواعيد ومهلات الاستبيان/)).toBeInTheDocument();
      expect(screen.getByText(/مهلة تذكير الاستبيان/)).toBeInTheDocument();
      expect(screen.getByText(/أقصى معدل إرسال بالدقيقة/)).toBeInTheDocument();
    });
  });
});
