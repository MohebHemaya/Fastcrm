import { useEffect, useRef, useState } from 'react';
import echo from '@/lib/echo';

export interface ConversationUpdatedPayload {
  driver_id: number;
  driver_name: string;
  last_message: string;
  unread_count: number;
  updated_at: string;
}

export interface InboundMessageAlertPayload {
  driver_id: number;
  message_id: number;
  body: string;
  direction: 'in';
  created_at: string;
}

interface UseWhatsAppInboxOptions {
  onConversationUpdated?: (payload: ConversationUpdatedPayload) => void;
  onMessageReceived?: (payload: InboundMessageAlertPayload) => void;
}

/**
 * useWhatsAppInbox
 *
 * Real-time WebSocket hook for the global WhatsApp conversations inbox.
 * Subscribes to private-whatsapp.inbox
 */
export function useWhatsAppInbox({
  onConversationUpdated,
  onMessageReceived,
}: UseWhatsAppInboxOptions = {}) {
  const [isConnected, setIsConnected] = useState<boolean>(false);
  const onConversationUpdatedRef = useRef(onConversationUpdated);
  const onMessageReceivedRef = useRef(onMessageReceived);

  useEffect(() => {
    onConversationUpdatedRef.current = onConversationUpdated;
  }, [onConversationUpdated]);

  useEffect(() => {
    onMessageReceivedRef.current = onMessageReceived;
  }, [onMessageReceived]);

  useEffect(() => {
    const channelName = 'whatsapp.inbox';
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

    // 1. Conversation summary updated (unread count, last message)
    channel.listen('.ConversationUpdated', (payload: ConversationUpdatedPayload) => {
      onConversationUpdatedRef.current?.(payload);
    });

    // 2. Inbound message arrived on inbox
    channel.listen('.MessageReceived', (payload: InboundMessageAlertPayload) => {
      onMessageReceivedRef.current?.(payload);
    });

    return () => {
      echo.leave(channelName);
      setIsConnected(false);
    };
  }, []);

  return { isConnected };
}

export default useWhatsAppInbox;
