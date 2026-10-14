import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { HealthPage } from '@/routes/HealthPage';
import { server } from '@/test/mocks/server';
import { http, HttpResponse } from 'msw';
import ar from '@/i18n/ar';

describe('HealthPage Component', () => {
  beforeEach(() => {
    // Reset any custom overrides
  });

  it('renders system health metrics, scheduler status, and failed jobs list', async () => {
    render(
      <MemoryRouter>
        <HealthPage />
      </MemoryRouter>
    );

    // Initial loading indicator
    expect(screen.getByText(/جاري فحص حالة النظام/i)).toBeInTheDocument();

    // After loading completes
    await waitFor(() => {
      expect(screen.getByText('مراقبة وصحة النظام')).toBeInTheDocument();
    });

    // Check components
    expect(screen.getByText(ar.health.scheduler)).toBeInTheDocument();
    expect(screen.getByText(ar.health.queue)).toBeInTheDocument();
    expect(screen.getByText(ar.health.whatsappSection)).toBeInTheDocument();
    expect(screen.getByText(ar.health.database)).toBeInTheDocument();

    // Check failed job row
    expect(screen.getByText('App\\Jobs\\SendWhatsAppMessageJob')).toBeInTheDocument();
    expect(screen.getByText(/Meta Graph API 500 Server Error/i)).toBeInTheDocument();
  });

  it('displays critical warning banner when scheduler heartbeat is stale (> 5 minutes)', async () => {
    server.use(
      http.get('/api/v1/admin/health', () => {
        return HttpResponse.json({
          data: {
            status: 'warning',
            checked_at: new Date().toISOString(),
            database: { status: 'ok', driver: 'mysql' },
            scheduler: {
              status: 'warning',
              last_heartbeat_at: new Date(Date.now() - 600000).toISOString(),
              age_seconds: 600,
              age_minutes: 10,
              is_stale: true,
              warning_threshold_minutes: 5,
            },
            queue: { backlog_size: 0, oldest_job_age_seconds: null, status: 'ok' },
            failed_jobs: { count: 0, recent: [] },
            webhook: { last_received_at: null, age_minutes: null },
            whatsapp: { provider: 'ultramsg', graph_version: 'v25.0', failed_messages_24h: 0 },
            backups: { count: 0, last_backup_at: null },
          },
          message: 'تقرير حالة النظام',
        });
      })
    );

    render(
      <MemoryRouter>
        <HealthPage />
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText(/تحذير: توقف مجدول المهام \(Cron\) لأكثر من 5 دقائق/i)).toBeInTheDocument();
    });
  });

  it('allows retrying a failed job', async () => {
    const user = userEvent.setup();

    render(
      <MemoryRouter>
        <HealthPage />
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('App\\Jobs\\SendWhatsAppMessageJob')).toBeInTheDocument();
    });

    const retryBtn = await screen.findByTestId('retry-job-99');
    expect(retryBtn).toBeInTheDocument();
    await user.click(retryBtn);
  });
});
