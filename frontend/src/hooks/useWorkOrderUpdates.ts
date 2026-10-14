import { useEffect, useRef, useState } from 'react';
import echo from '@/lib/echo';

export interface WorkOrderUpdatedPayload {
  work_order_id: number;
  status: string;
  confirmed_count: number;
  backup_count: number;
  trips_needed: number;
  backup_needed: number;
  is_filled: boolean;
  driver_id?: number | null;
  recipient_id?: number | null;
  recipient_status?: string | null;
  message?: string;
  updated_at: string;
}

interface UseWorkOrderUpdatesOptions {
  workOrderId?: number;
  onOrderUpdated?: (payload: WorkOrderUpdatedPayload) => void;
}

/**
 * Plays a pleasant notification chime using the Web Audio API without requiring external audio assets.
 */
export function playSuccessChime() {
  try {
    const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioContextClass) return;
    const ctx = new AudioContextClass();
    const now = ctx.currentTime;

    // Harmonic two-tone chord chime (E5 -> A5)
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(659.25, now);
    gain1.gain.setValueAtTime(0.15, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.35);

    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(880, now + 0.12);
    gain2.gain.setValueAtTime(0.2, now + 0.12);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.12);
    osc2.stop(now + 0.6);
  } catch (e) {
    console.debug('[Audio] Web Audio chime could not play', e);
  }
}

/**
 * Hook to subscribe to Pusher real-time updates for work orders.
 * If workOrderId is provided, subscribes to private `work-order.${workOrderId}`.
 * Otherwise, subscribes to private `work-orders` for system-wide updates.
 */
export function useWorkOrderUpdates({
  workOrderId,
  onOrderUpdated,
}: UseWorkOrderUpdatesOptions = {}) {
  const [isConnected, setIsConnected] = useState<boolean>(false);
  const onOrderUpdatedRef = useRef(onOrderUpdated);

  useEffect(() => {
    onOrderUpdatedRef.current = onOrderUpdated;
  }, [onOrderUpdated]);

  useEffect(() => {
    const channelName = workOrderId ? `work-order.${workOrderId}` : 'work-orders';
    const channel = echo.private(channelName);

    try {
      const pusher = (echo.connector as any)?.pusher;
      if (pusher?.connection?.state === 'connected') {
        setIsConnected(true);
      }
      pusher?.connection?.bind('connected', () => setIsConnected(true));
      pusher?.connection?.bind('disconnected', () => setIsConnected(false));
      pusher?.connection?.bind('error', () => setIsConnected(false));
    } catch {
      // Ignore
    }

    const handler = (payload: WorkOrderUpdatedPayload) => {
      onOrderUpdatedRef.current?.(payload);
    };

    channel.listen('.WorkOrderUpdated', handler);
    channel.listen('WorkOrderUpdated', handler);

    return () => {
      echo.leave(channelName);
      setIsConnected(false);
    };
  }, [workOrderId]);

  return { isConnected };
}

export default useWorkOrderUpdates;
