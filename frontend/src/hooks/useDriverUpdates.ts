import { useEffect, useRef, useState } from 'react';
import echo from '@/lib/echo';

export interface DriverStatusUpdatedPayload {
  driver_id: number;
  driver_name: string;
  old_status: string;
  new_status: string;
  updated_at: string;
}

export interface QuestionnaireProgressPayload {
  driver_id: number;
  question_text: string;
  answer: string;
  answered_count: number;
  total_questions: number;
  updated_at: string;
}

interface UseDriverUpdatesOptions {
  onStatusUpdated?: (payload: DriverStatusUpdatedPayload) => void;
  onQuestionnaireProgress?: (payload: QuestionnaireProgressPayload) => void;
}

/**
 * useDriverUpdates
 *
 * Real-time WebSocket hook for driver-wide events (status changes, questionnaire progress).
 * Subscribes to private-drivers
 */
export function useDriverUpdates({
  onStatusUpdated,
  onQuestionnaireProgress,
}: UseDriverUpdatesOptions = {}) {
  const [isConnected, setIsConnected] = useState<boolean>(false);
  const onStatusUpdatedRef = useRef(onStatusUpdated);
  const onQuestionnaireProgressRef = useRef(onQuestionnaireProgress);

  useEffect(() => {
    onStatusUpdatedRef.current = onStatusUpdated;
  }, [onStatusUpdated]);

  useEffect(() => {
    onQuestionnaireProgressRef.current = onQuestionnaireProgress;
  }, [onQuestionnaireProgress]);

  useEffect(() => {
    const channelName = 'drivers';
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

    channel.listen('.DriverStatusUpdated', (payload: DriverStatusUpdatedPayload) => {
      onStatusUpdatedRef.current?.(payload);
    });

    channel.listen('.QuestionnaireProgressUpdated', (payload: QuestionnaireProgressPayload) => {
      onQuestionnaireProgressRef.current?.(payload);
    });

    return () => {
      echo.leave(channelName);
      setIsConnected(false);
    };
  }, []);

  return { isConnected };
}

export default useDriverUpdates;
