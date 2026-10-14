import { useEffect, useRef, useCallback } from 'react';

interface SmartPollingOptions {
  /** Main message poll interval in ms (default 5000) */
  messageIntervalMs?: number;
  /** Conversation-list refresh interval in ms (default 8000) */
  conversationIntervalMs?: number;
  /** Pause polling when tab is hidden via Page Visibility API (default true) */
  pauseOnHidden?: boolean;
}

interface SmartPollingCallbacks {
  /** Fetch new messages (incremental) for the currently open conversation */
  onPollMessages: () => Promise<void>;
  /** Refresh the conversations sidebar list */
  onPollConversations: () => Promise<void>;
}

/**
 * useSmartInboxPolling
 *
 * Runs two separate polling cycles:
 *  1. Fast cycle  → onPollMessages      (~5 s) — only when a conversation is open
 *  2. Slow cycle  → onPollConversations (~8 s) — always while page is visible
 *
 * Both cycles automatically PAUSE when the browser tab is hidden (Page Visibility API),
 * preventing unnecessary DB hits while the operator is not looking at the inbox.
 */
export function useSmartInboxPolling(
  callbacks: SmartPollingCallbacks,
  selectedDriverId: number | null,
  options: SmartPollingOptions = {},
) {
  const {
    messageIntervalMs = 5000,
    conversationIntervalMs = 8000,
    pauseOnHidden = true,
  } = options;

  const { onPollMessages, onPollConversations } = callbacks;

  const msgTimerRef  = useRef<ReturnType<typeof setInterval> | null>(null);
  const convTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const isHiddenRef  = useRef(false);

  const clearTimers = useCallback(() => {
    if (msgTimerRef.current)  clearInterval(msgTimerRef.current);
    if (convTimerRef.current) clearInterval(convTimerRef.current);
    msgTimerRef.current  = null;
    convTimerRef.current = null;
  }, []);

  const startTimers = useCallback(() => {
    clearTimers();

    // Fast cycle: incremental message poll (only when a driver is selected)
    if (selectedDriverId !== null) {
      msgTimerRef.current = setInterval(() => {
        if (!isHiddenRef.current) {
          onPollMessages().catch(() => {});
        }
      }, messageIntervalMs);
    }

    // Slow cycle: conversations list refresh
    convTimerRef.current = setInterval(() => {
      if (!isHiddenRef.current) {
        onPollConversations().catch(() => {});
      }
    }, conversationIntervalMs);
  }, [
    selectedDriverId,
    messageIntervalMs,
    conversationIntervalMs,
    onPollMessages,
    onPollConversations,
    clearTimers,
  ]);

  // Restart timers whenever selectedDriverId changes
  useEffect(() => {
    startTimers();
    return clearTimers;
  }, [startTimers, clearTimers]);

  // Page Visibility API — pause/resume polling automatically
  useEffect(() => {
    if (!pauseOnHidden || typeof document === 'undefined') return;

    const handleVisibilityChange = () => {
      isHiddenRef.current = document.hidden;
      if (!document.hidden) {
        // Tab became visible again — poll immediately then restart timers
        onPollMessages().catch(() => {});
        onPollConversations().catch(() => {});
        startTimers();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [pauseOnHidden, onPollMessages, onPollConversations, startTimers]);
}
