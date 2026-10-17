import { useEffect, useRef, useState, useCallback } from 'react';
import echo from '@/lib/echo';
import type { WhatsAppMessage } from '@/types/whatsapp';

export interface DriverChatMessagePayload {
  driver_id: number;
  message_id: number;
  body: string;
  direction: 'in' | 'out';
  created_at: string;
}

export interface QuestionnaireProgressPayload {
  driver_id: number;
  question_text: string;
  answer: string;
  answered_count: number;
  total_questions: number;
  updated_at: string;
}

interface UseDriverChatOptions {
  driverId: number | null;
  onNewMessage?: (msg: WhatsAppMessage) => void;
  onQuestionnaireProgress?: (progress: QuestionnaireProgressPayload) => void;
  onStatusUpdated?: (update: import('@/types/whatsapp').MessageStatusUpdatedPayload) => void;
}

/**
 * useDriverChat
 *
 * Real-time WebSocket hook via Pusher / Laravel Echo for a specific driver's WhatsApp chat.
 * Subscribes to private-whatsapp.driver.{driverId}
 */
export function useDriverChat({
  driverId,
  onNewMessage,
  onQuestionnaireProgress,
  onStatusUpdated,
}: UseDriverChatOptions) {
  const [isConnected, setIsConnected] = useState<boolean>(false);
  const onNewMessageRef = useRef(onNewMessage);
  const onQuestionnaireProgressRef = useRef(onQuestionnaireProgress);
  const onStatusUpdatedRef = useRef(onStatusUpdated);

  useEffect(() => {
    onNewMessageRef.current = onNewMessage;
  }, [onNewMessage]);

  useEffect(() => {
    onQuestionnaireProgressRef.current = onQuestionnaireProgress;
  }, [onQuestionnaireProgress]);

  useEffect(() => {
    onStatusUpdatedRef.current = onStatusUpdated;
  }, [onStatusUpdated]);

  const convertToWhatsAppMessage = useCallback((payload: DriverChatMessagePayload): WhatsAppMessage => {
    return {
      id: payload.message_id,
      driver_id: payload.driver_id,
      direction: payload.direction,
      direction_label: payload.direction === 'in' ? 'واردة' : 'صادرة',
      type: 'text',
      body: payload.body,
      status: payload.direction === 'in' ? 'delivered' : 'sent',
      status_label: payload.direction === 'in' ? 'مستلمة' : 'مرسلة',
      created_at: payload.created_at,
    };
  }, []);

  useEffect(() => {
    if (!driverId) {
      setIsConnected(false);
      return;
    }

    const channelName = `whatsapp.driver.${driverId}`;
    const channel = echo.private(channelName);

    // Track connection state
    try {
      const pusher = (echo.connector as any)?.pusher;
      if (pusher?.connection?.state === 'connected') {
        setIsConnected(true);
      }
      pusher?.connection?.bind('connected', () => setIsConnected(true));
      pusher?.connection?.bind('disconnected', () => setIsConnected(false));
      pusher?.connection?.bind('error', () => setIsConnected(false));
    } catch {
      // Echo connector may vary in test environments
    }

    // 1. Inbound message received from driver
    channel.listen('.MessageReceived', (payload: DriverChatMessagePayload) => {
      const msg = convertToWhatsAppMessage(payload);
      onNewMessageRef.current?.(msg);
    });

    // 2. Outbound message sent to driver
    channel.listen('.MessageSent', (payload: DriverChatMessagePayload) => {
      const msg = convertToWhatsAppMessage(payload);
      onNewMessageRef.current?.(msg);
    });

    // 3. Status updated event (ticks: sent -> delivered -> read)
    channel.listen('.MessageStatusUpdated', (payload: import('@/types/whatsapp').MessageStatusUpdatedPayload) => {
      onStatusUpdatedRef.current?.(payload);
    });

    // 4. Questionnaire progress event
    channel.listen('.QuestionnaireProgressUpdated', (payload: QuestionnaireProgressPayload) => {
      onQuestionnaireProgressRef.current?.(payload);
    });

    return () => {
      echo.leave(channelName);
      setIsConnected(false);
    };
  }, [driverId, convertToWhatsAppMessage]);

  return { isConnected };
}

export default useDriverChat;
