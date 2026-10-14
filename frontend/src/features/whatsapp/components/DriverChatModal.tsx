import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Spinner,
  Chip,
  Tooltip,
} from '@heroui/react';
import {
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  ArrowPathIcon,
  CheckIcon,
  ClockIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type { WhatsAppMessage, DriverChatMeta } from '@/types/whatsapp';
import { useIsMobile } from '@/hooks/useMediaQuery';
import { useDriverChat } from '@/hooks/useDriverChat';

interface DriverChatModalProps {
  driverId: number | null;
  driverName?: string | null;
  driverPhone?: string;
  isOpen: boolean;
  onClose: () => void;
  onStatusChanged?: () => void;
}

export const DriverChatModal: React.FC<DriverChatModalProps> = ({
  driverId,
  driverName,
  driverPhone,
  isOpen,
  onClose,
  onStatusChanged,
}) => {
  const isMobile = useIsMobile();
  const [messages, setMessages] = useState<WhatsAppMessage[]>([]);
  const [meta, setMeta] = useState<DriverChatMeta | null>(null);
  const [replyText, setReplyText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [retryingMessageId, setRetryingMessageId] = useState<number | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const lastMessageIdRef = useRef<number>(0);
  const isPollingRef = useRef<boolean>(false);
  const addToast = useUiStore((state) => state.addToast);

  const scrollToBottom = (smooth = true) => {
    messagesEndRef.current?.scrollIntoView?.({ behavior: smooth ? 'smooth' : 'auto' });
  };

  const loadMessages = async (id: number) => {
    setIsLoading(true);
    try {
      const res = await whatsappService.getDriverMessages(id);
      const msgs = res.data || [];
      setMessages(msgs);
      setMeta(res.meta);
      if (msgs.length > 0) {
        lastMessageIdRef.current = Math.max(...msgs.map((m) => m.id));
      }
      setTimeout(() => scrollToBottom(false), 80);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsLoading(false);
    }
  };

  const pollIncremental = useCallback(async (id: number) => {
    if (isPollingRef.current) return;
    isPollingRef.current = true;
    try {
      const res = await whatsappService.getNewMessages(id, lastMessageIdRef.current);
      const newMsgs = res.data || [];
      const updatedStatuses = res.meta?.statuses;

      const newStatus = res.meta?.driver_status;
      if (newStatus) {
        setMeta((prev) => {
          if (!prev) return prev;
          if (prev.driver_status !== newStatus) {
            onStatusChanged?.();
            return {
              ...prev,
              driver_status: newStatus,
              can_message: res.meta?.can_message ?? prev.can_message,
            };
          }
          return prev;
        });
      }

      setMessages((prev) => {
        let hasChanges = false;
        let updatedList = prev;

        // 1. Sync delivery ticks on existing outbound messages
        if (updatedStatuses && updatedStatuses.length > 0) {
          const statusMap = new Map(updatedStatuses.map((s) => [s.id, s]));
          updatedList = updatedList.map((m) => {
            const st = statusMap.get(m.id);
            if (st && (st.status !== m.status || st.delivered_at !== m.delivered_at || st.read_at !== m.read_at)) {
              hasChanges = true;
              return {
                ...m,
                status: st.status,
                delivered_at: st.delivered_at ?? m.delivered_at,
                read_at: st.read_at ?? m.read_at,
                error_message: st.error_message ?? m.error_message,
              };
            }
            return m;
          });
        }

        // 2. Append newly arrived messages
        if (newMsgs.length > 0) {
          const existingIds = new Set(updatedList.map((m) => m.id));
          const fresh = newMsgs.filter((m) => !existingIds.has(m.id));
          if (fresh.length > 0) {
            hasChanges = true;
            updatedList = [...updatedList, ...fresh];
            const maxId = Math.max(...fresh.map((m) => m.id));
            if (maxId > lastMessageIdRef.current) {
              lastMessageIdRef.current = maxId;
            }
            setTimeout(() => scrollToBottom(true), 50);
          }
        }

        return hasChanges ? updatedList : prev;
      });
    } catch {
      // background poll errors are swallowed silently
    } finally {
      isPollingRef.current = false;
    }
  }, [onStatusChanged]);

  const handlePusherNewMessage = useCallback((msg: WhatsAppMessage) => {
    setMessages((prev) => {
      const exists = prev.some((m) => m.id === msg.id);
      if (exists) {
        return prev.map((m) => (m.id === msg.id ? { ...m, ...msg } : m));
      }
      return [...prev, msg];
    });
    if (msg.id > lastMessageIdRef.current) {
      lastMessageIdRef.current = msg.id;
    }
    setTimeout(() => scrollToBottom(true), 60);
  }, []);

  const { isConnected: isPusherConnected } = useDriverChat({
    driverId: isOpen ? driverId : null,
    onNewMessage: handlePusherNewMessage,
  });

  useEffect(() => {
    if (isOpen && driverId) {
      loadMessages(driverId);
      // Fallback: relaxed 15s poll when Pusher is live, faster 3s fallback if disconnected
      const pollDelay = isPusherConnected ? 15000 : 3000;
      const interval = setInterval(() => {
        if (document.visibilityState === 'visible' && !isSending) {
          pollIncremental(driverId);
        }
      }, pollDelay);

      return () => clearInterval(interval);
    } else {
      setMessages([]);
      setMeta(null);
      lastMessageIdRef.current = 0;
    }
  }, [isOpen, driverId, pollIncremental, isSending, isPusherConnected]);

  const handleRetry = async (messageId: number) => {
    setRetryingMessageId(messageId);
    try {
      await whatsappService.retryMessage(messageId);
      addToast({
        title: ar.whatsapp.retrySuccess,
        message: ar.whatsapp.retrySuccess,
        type: 'success',
      });
      if (driverId) {
        await loadMessages(driverId);
      }
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setRetryingMessageId(null);
    }
  };

  const handleSendMessage = async () => {
    if (!driverId || !replyText.trim() || isSending) return;
    const textToSend = replyText.trim();
    setIsSending(true);
    try {
      await whatsappService.sendDirectMessage(driverId, textToSend);
      setReplyText('');
      await loadMessages(driverId);
      onStatusChanged?.();
    } catch (e: any) {
      addToast({
        title: ar.errors.generalError,
        message: e?.response?.data?.message || 'فشل إرسال الرسالة',
        type: 'error',
      });
    } finally {
      setIsSending(false);
    }
  };

  const renderStatusIcon = (msg: WhatsAppMessage) => {
    if (msg.direction === 'in') return null;

    switch (msg.status) {
      case 'queued':
        return (
          <Tooltip content={ar.whatsapp.status.queued}>
            <ClockIcon className="w-3.5 h-3.5 text-warning-500 inline-block ms-1" />
          </Tooltip>
        );
      case 'sent':
        return (
          <Tooltip content={ar.whatsapp.status.sent}>
            <CheckIcon className="w-3.5 h-3.5 text-default-400 inline-block ms-1" />
          </Tooltip>
        );
      case 'delivered':
        return (
          <Tooltip content={ar.whatsapp.status.delivered}>
            <div className="inline-flex -space-x-1.5 ms-1">
              <CheckIcon className="w-3.5 h-3.5 text-default-400" />
              <CheckIcon className="w-3.5 h-3.5 text-default-400" />
            </div>
          </Tooltip>
        );
      case 'read':
        return (
          <Tooltip content={ar.whatsapp.status.read}>
            <div className="inline-flex -space-x-1.5 ms-1">
              <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
              <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
            </div>
          </Tooltip>
        );
      case 'failed':
        return (
          <div className="inline-flex items-center gap-1 ms-1">
            <Tooltip content={msg.error_message || ar.whatsapp.status.failed}>
              <ExclamationCircleIcon className="w-3.5 h-3.5 text-danger-500" />
            </Tooltip>
            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="danger"
              className="w-5 h-5 min-w-5 p-0"
              isLoading={retryingMessageId === msg.id}
              onClick={() => handleRetry(msg.id)}
              title={ar.whatsapp.retry}
            >
              <ArrowPathIcon className="w-3 h-3" />
            </Button>
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size={isMobile ? 'full' : '2xl'}
      scrollBehavior="inside"
      classNames={{
        base: isMobile ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none' : '',
      }}
    >
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1 border-b border-default-200 pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-9 h-9 rounded-full bg-success-50 text-success-600 flex items-center justify-center">
                <ChatBubbleLeftRightIcon className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-base font-bold text-default-900">
                  {meta?.driver_name || driverName || ar.drivers.unnamed}
                </h3>
                <p className="text-xs text-default-500 font-mono" dir="ltr">
                  {meta?.driver_phone || driverPhone}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              {isPusherConnected && (
                <Chip size="sm" variant="dot" color="success" className="text-[11px] h-6">
                  مباشر
                </Chip>
              )}
              {meta && (
                <Chip size="sm" variant="flat" color={meta.can_message ? 'success' : 'danger'}>
                  {meta.driver_status}
                </Chip>
              )}
            </div>
          </div>
        </ModalHeader>

        <ModalBody className={`p-0 bg-default-50 flex flex-col ${isMobile ? 'flex-1 h-full min-h-0' : 'h-[480px]'}`}>
          {/* Opt-out or blocked banner */}
          {meta && !meta.can_message && (
            <div className="p-3 bg-danger-50 border-b border-danger-200 text-danger-700 text-xs flex items-center gap-2">
              <ExclamationTriangleIcon className="w-4 h-4 shrink-0" />
              <span>{ar.whatsapp.optedOutNotice}</span>
            </div>
          )}

          {/* Messages list */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {isLoading ? (
              <div className="h-full flex items-center justify-center">
                <Spinner label={ar.app.loading} color="success" />
              </div>
            ) : messages.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-default-400 gap-2">
                <ChatBubbleLeftRightIcon className="w-10 h-10 stroke-1 opacity-50" />
                <p className="text-sm">{ar.whatsapp.noMessagesYet}</p>
              </div>
            ) : (
              messages.map((msg) => {
                const isOutbound = msg.direction === 'out';
                return (
                  <div
                    key={msg.id}
                    className={`flex flex-col ${
                      isOutbound ? 'items-end' : 'items-start'
                    }`}
                  >
                    <div
                      className={`max-w-[85%] sm:max-w-[80%] rounded-2xl px-4 py-2.5 shadow-sm text-sm ${
                        isOutbound
                          ? 'bg-success-100/70 text-default-900 rounded-ee-none border border-success-200/50'
                          : 'bg-white text-default-900 rounded-es-none border border-default-200/60'
                      }`}
                    >
                      {/* Template tag */}
                      {msg.template_name && (
                        <div className="text-[10px] text-default-500 font-semibold mb-1 flex items-center gap-1">
                          <span className="bg-default-200/60 px-1.5 py-0.5 rounded">
                            {msg.template_name}
                          </span>
                        </div>
                      )}

                      {/* Message body */}
                      <p className="whitespace-pre-wrap leading-relaxed">{msg.body}</p>

                      {/* Footer: Time & Status */}
                      <div className="flex items-center justify-end gap-1 mt-1 text-[11px] text-default-400">
                        <span>
                          {new Date(msg.created_at).toLocaleTimeString('ar-EG', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </span>
                        {renderStatusIcon(msg)}
                      </div>
                    </div>
                  </div>
                );
              })
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Real WhatsApp Message Composer */}
          <div className="border-t border-default-200 bg-white dark:bg-content1 p-3">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSendMessage();
              }}
              className="flex items-center gap-2"
            >
              <Input
                size="sm"
                variant="bordered"
                placeholder="اكتب ردك للسائق هنا عبر واتساب..."
                value={replyText}
                onChange={(e) => setReplyText(e.target.value)}
                disabled={isSending}
                className="flex-1 text-xs"
              />
              <Button
                size="sm"
                color="primary"
                type="submit"
                isIconOnly
                isLoading={isSending}
                disabled={!replyText.trim() || isSending}
                aria-label="إرسال"
                className="bg-emerald-600 hover:bg-emerald-700 text-white min-w-[36px] min-h-[36px]"
              >
                <PaperAirplaneIcon className="w-4 h-4 rtl:rotate-180" />
              </Button>
            </form>
          </div>
        </ModalBody>

        <ModalFooter className="border-t border-default-200 py-2.5 safe-bottom">
          <Button variant="light" size="sm" className="min-h-[44px] w-full sm:w-auto" onClick={onClose}>
            {ar.common.close}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default DriverChatModal;
