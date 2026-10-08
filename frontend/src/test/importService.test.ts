import { describe, it, expect } from 'vitest';
import importService from '@/services/importService';

describe('importService', () => {
  it('uploads file and returns import record', async () => {
    const file = new File(['phone,group\n01012345678,cairo'], 'test.csv', { type: 'text/csv' });
    const res = await importService.uploadImport(file);
    expect(res.data.id).toBe(42);
    expect(res.data.status).toBe('parsing');
  });

  it('fetches import details with counters', async () => {
    const res = await importService.getImport(42);
    expect(res.data.id).toBe(42);
    expect(res.data.status).toBe('ready_for_review');
    expect(res.data.counts.total).toBe(20);
    expect(res.data.counts.new).toBe(15);
  });

  it('fetches staged import rows with filter', async () => {
    const res = await importService.getImportRows(42, { classification: 'new' });
    expect(res.data).toHaveLength(1);
    expect(res.data[0].classification).toBe('new');
    expect(res.data[0].normalized_phone).toBe('+201012345678');
  });

  it('commits import successfully', async () => {
    const res = await importService.commitImport(42);
    expect(res.data.status).toBe('completed');
  });

  it('cancels import successfully', async () => {
    const res = await importService.cancelImport(42);
    expect(res.data.status).toBe('cancelled');
  });

  it('generates correct template and export URLs', () => {
    expect(importService.getTemplateUrl()).toBe('/api/v1/imports/template');
    expect(importService.getExportInvalidUrl(42)).toBe('/api/v1/imports/42/export-invalid');
  });
});
