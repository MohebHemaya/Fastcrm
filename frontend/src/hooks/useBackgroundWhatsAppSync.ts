import { useEffect, useRef } from 'react';
import { whatsappService } from '@/services/whatsappService';
import { useAuthStore } from '@/lib/stores/useAuthStore';

const SYNC_INTERVAL_MS = 15_000; // 15 seconds (keeps PHP server responsive)

/**
 * Global background poller hook.
 * Keeps WhatsApp bots and questionnaires running automatically in the background
 * regardless of which page the user is viewing.
 */
export function useBackgroundWhatsAppSync(onNewMessage?: () => void) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const isSyncingRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated) return;

    const runSync = async () => {
      if (isSyncingRef.current || document.hidden) return;
      isSyncingRef.current = true;
      try {
        const res = await whatsappService.syncActiveInbound();
        if (res?.data?.processed_messages > 0) {
          onNewMessage?.();
        }
      } catch {
        // Background polling errors are ignored
      } finally {
        isSyncingRef.current = false;
      }
    };

    // Initial sync
    runSync();

    const interval = setInterval(runSync, SYNC_INTERVAL_MS);

    const handleVisibilityChange = () => {
      if (!document.hidden) {
        runSync();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [isAuthenticated, onNewMessage]);
}
