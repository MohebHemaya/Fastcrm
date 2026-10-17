import React from 'react';
import { useDriverUpdates } from '@/hooks/useDriverUpdates';
import { useWhatsAppInbox } from '@/hooks/useWhatsAppInbox';
import { useUiStore } from '@/lib/stores/useUiStore';
import { ar } from '@/i18n/ar';

/**
 * RealtimeNotifications
 *
 * Global listener mounted in AppShell.
 * Listens to driver status changes and incoming WhatsApp messages,
 * providing instant visual toasts.
 */
export const RealtimeNotifications: React.FC = () => {
  const addToast = useUiStore((state) => state.addToast);

  // 1. Driver status updates
  useDriverUpdates({
    onStatusUpdated: (payload) => {
      const statusText = (ar.statuses as Record<string, string>)[payload.new_status] || payload.new_status;
      const driverName = payload.driver_name ? `السائق ${payload.driver_name}` : 'سائق';

      addToast({
        title: 'تحديث حالة السائق',
        message: `تم تحديث حالة ${driverName} إلى: ${statusText}`,
        type: 'info',
        duration: 4000,
      });
    },
    onQuestionnaireProgress: (payload) => {
      addToast({
        title: 'إجابة جديدة على الاستبيان',
        message: `سؤال: ${payload.question_text}, الإجابة: ${payload.answer}`,
        type: 'info',
        duration: 3500,
      });
    },
  });

  // 2. Incoming WhatsApp messages
  useWhatsAppInbox({
    onMessageReceived: (payload) => {
      const snippet = payload.body
        ? payload.body.length > 60
          ? payload.body.substring(0, 60) + '...'
          : payload.body
        : 'رسالة جديدة';

      addToast({
        title: 'رسالة واتساب واردة',
        message: snippet,
        type: 'info',
        duration: 5000,
      });
    },
  });

  return null;
};

export default RealtimeNotifications;
