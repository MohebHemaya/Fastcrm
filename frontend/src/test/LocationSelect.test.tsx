import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import CreateWorkOrderPage from '@/routes/CreateWorkOrderPage';
import { ar } from '@/i18n/ar';
import {
  EGYPT_GOVERNORATES,
  getCitiesForGovernorate,
  formatLocationString,
  parseLocationString,
} from '@/data/egyptLocations';

describe('Location Selects & Egyptian Cities Module', () => {
  it('contains all 27 Egyptian governorates', () => {
    expect(EGYPT_GOVERNORATES.length).toBe(27);
    expect(EGYPT_GOVERNORATES.some((g) => g.key === 'القاهرة')).toBe(true);
    expect(EGYPT_GOVERNORATES.some((g) => g.key === 'الشرقية')).toBe(true);
    expect(EGYPT_GOVERNORATES.some((g) => g.key === 'الإسكندرية')).toBe(true);
    expect(EGYPT_GOVERNORATES.some((g) => g.key === 'أسوان')).toBe(true);
  });

  it('populates cities for a given governorate with fallback "other" option', () => {
    const sharkiaCities = getCitiesForGovernorate('الشرقية');
    expect(sharkiaCities.length).toBeGreaterThan(5);
    expect(sharkiaCities.some((c) => c.key === 'العاشر من رمضان')).toBe(true);
    expect(sharkiaCities.some((c) => c.key === 'أخرى')).toBe(true);

    const alexCities = getCitiesForGovernorate('الإسكندرية');
    expect(alexCities.some((c) => c.key === 'برج العرب')).toBe(true);
    expect(alexCities.some((c) => c.key === 'ميناء الدخيلة')).toBe(true);
  });

  it('correctly formats and parses location strings bidirectionally', () => {
    const formatted1 = formatLocationString('الشرقية', 'العاشر من رمضان');
    expect(formatted1).toBe('الشرقية - العاشر من رمضان');

    const formatted2 = formatLocationString('القاهرة', 'مدينة نصر', 'شارع مكرم عبيد');
    expect(formatted2).toBe('القاهرة - مدينة نصر (شارع مكرم عبيد)');

    const parsed1 = parseLocationString('الشرقية - العاشر من رمضان');
    expect(parsed1.governorate).toBe('الشرقية');
    expect(parsed1.city).toBe('العاشر من رمضان');
    expect(parsed1.details).toBeUndefined();

    const parsed2 = parseLocationString('القاهرة - مدينة نصر (شارع مكرم عبيد)');
    expect(parsed2.governorate).toBe('القاهرة');
    expect(parsed2.city).toBe('مدينة نصر');
    expect(parsed2.details).toBe('شارع مكرم عبيد');
  });

  it('renders governorate and city selects on CreateWorkOrderPage Step 1', () => {
    render(
      <HeroUIProvider>
        <MemoryRouter>
          <CreateWorkOrderPage />
        </MemoryRouter>
      </HeroUIProvider>
    );

    // Verify departure location labels
    expect(screen.getByText(ar.workOrders.fields.fromGovernorate)).toBeInTheDocument();
    expect(screen.getByText(ar.workOrders.fields.fromCity)).toBeInTheDocument();

    // Verify destination location labels
    expect(screen.getByText(ar.workOrders.fields.toGovernorate)).toBeInTheDocument();
    expect(screen.getByText(ar.workOrders.fields.toCity)).toBeInTheDocument();

    // Both departure and destination have details inputs
    expect(screen.getAllByText(ar.workOrders.fields.fromDetails)).toHaveLength(2);
  });
});
