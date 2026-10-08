import { describe, it, expect } from 'vitest';
import driverService from '@/services/driverService';

describe('driverService', () => {
  it('fetches paginated drivers list', async () => {
    const res = await driverService.getDrivers({ page: 1 });
    expect(res.data).toHaveLength(2);
    expect(res.data[0].phone).toBe('+201012345678');
    expect(res.meta.total).toBe(2);
  });

  it('filters drivers by status', async () => {
    const res = await driverService.getDrivers({ status: 'active' });
    expect(res.data).toHaveLength(1);
    expect(res.data[0].status.value).toBe('active');
  });

  it('fetches a single driver details with status histories', async () => {
    const res = await driverService.getDriver(1);
    expect(res.data.id).toBe(1);
    expect(res.data.status.value).toBe('active');
    expect(res.data.groups).toHaveLength(1);
    expect(res.data.status_histories).toHaveLength(1);
  });

  it('creates a new driver manually', async () => {
    const res = await driverService.createDriver({
      phone: '+201099887766',
      name: 'محمود حسن',
      notes: 'سائق جديد',
    });
    expect(res.data.id).toBe(10);
    expect(res.data.phone).toBe('+201099887766');
    expect(res.data.status.value).toBe('new');
  });

  it('updates driver name and notes', async () => {
    const res = await driverService.updateDriver(1, {
      name: 'أحمد محمود المعدل',
      notes: 'ملاحظات محدثة',
    });
    expect(res.data.name).toBe('أحمد محمود المعدل');
  });

  it('changes driver status through state machine endpoint', async () => {
    const res = await driverService.changeStatus(1, {
      status: 'suspended',
      reason: 'مخالفة الشروط',
    });
    expect(res.data.status.value).toBe('suspended');
  });

  it('fetches whatsapp groups', async () => {
    const res = await driverService.getWhatsappGroups();
    expect(res.data).toHaveLength(2);
    expect(res.data[0].name).toBe('سائقين القاهرة');
  });
});
