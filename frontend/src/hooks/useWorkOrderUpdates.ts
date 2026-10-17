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
