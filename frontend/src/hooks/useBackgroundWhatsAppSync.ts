import { useEffect, useRef } from 'react';
import { whatsappService } from '@/services/whatsappService';
import { useAuthStore } from '@/lib/stores/useAuthStore';
import { echo } from '@/lib/echo';

const WS_CONNECTED_SYNC_INTERVAL_MS = 60_000; // 60s safety fallback when WebSocket is live
const FALLBACK_SYNC_INTERVAL_MS = 20_000;      // 20s when WebSocket is disconnected

/**
 * Global background sync hook.
 * Keeps WhatsApp bots and questionnaires running smoothly.
 * Adapts intelligently: when Pusher WebSocket is connected, reduces HTTP polling
 * to a lightweight 60s safety net, preventing unnecessary PHP server load.
 */
export function useBackgroundWhatsAppSync(onNewMessage?: () => void) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const isSyncingRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated) return;

    const isWsConnected = () => {
      try {
        const pusher = (echo?.connector as unknown as { pusher?: { connection?: { state?: string } } })?.pusher;
        return pusher?.connection?.state === 'connected';
      } catch {
        return false;
      }
    };

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

    // Defer initial sync by 4 seconds so page loads and renders instantly without network contention
    const initialTimer = setTimeout(runSync, 4000);
    let timerId: ReturnType<typeof setTimeout>;

    const scheduleNext = () => {
      const nextInterval = isWsConnected() ? WS_CONNECTED_SYNC_INTERVAL_MS : FALLBACK_SYNC_INTERVAL_MS;
      timerId = setTimeout(async () => {
        await runSync();
        scheduleNext();
      }, nextInterval);
    };

    scheduleNext();

    const handleVisibilityChange = () => {
      if (!document.hidden) {
        runSync();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      clearTimeout(initialTimer);
      clearTimeout(timerId);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [isAuthenticated, onNewMessage]);
}
