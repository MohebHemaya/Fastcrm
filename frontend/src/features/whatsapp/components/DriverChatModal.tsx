import React, { useState, useEffect, useRef } from 'react';
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
  SparklesIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type { WhatsAppMessage, DriverChatMeta } from '@/types/whatsapp';
import { useIsMobile } from '@/hooks/useMediaQuery';

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
  const [isLoading, setIsLoading] = useState(false);
  const [isSimulating, setIsSimulating] = useState(false);
  const [customReplyText, setCustomReplyText] = useState('');
  const [retryingMessageId, setRetryingMessageId] = useState<number | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const addToast = useUiStore((state) => state.addToast);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView?.({ behavior: 'smooth' });
  };

  const loadMessages = async (id: number) => {
    setIsLoading(true);
    try {
      const res = await whatsappService.getDriverMessages(id);
      setMessages(res.data);
      setMeta(res.meta);
      setTimeout(scrollToBottom, 100);
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

  useEffect(() => {
    if (isOpen && driverId) {
      loadMessages(driverId);
    } else {
      setMessages([]);
      setMeta(null);
    }
  }, [isOpen, driverId]);

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

  const handleSimulate = async (text: string, payloadId?: string) => {
    const phone = meta?.driver_phone || driverPhone;
    if (!phone) return;

    setIsSimulating(true);
    try {
      await whatsappService.simulateInbound(phone, text, payloadId);
      addToast({
        title: ar.whatsapp.simulateSuccess,
        message: ar.whatsapp.simulateSuccess,
        type: 'success',
      });
      setCustomReplyText('');
      if (driverId) {
        await loadMessages(driverId);
      }
      onStatusChanged?.();
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsSimulating(false);
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
            {meta && (
              <Chip size="sm" variant="flat" color={meta.can_message ? 'success' : 'danger'}>
                {meta.driver_status}
              </Chip>
            )}
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

          {/* Dev Simulation Toolbar */}
          <div className="border-t border-default-200 bg-white p-3 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-default-600 flex items-center gap-1">
                <SparklesIcon className="w-3.5 h-3.5 text-secondary-500" />
                {ar.whatsapp.simulateIncoming}:
              </span>
              <div className="flex items-center gap-1.5">
                <Button
                  size="sm"
                  variant="flat"
                  color="success"
                  className="h-7 text-xs"
                  isLoading={isSimulating}
                  onClick={() => handleSimulate('نعم، مهتم', 'invite_yes')}
                >
                  {ar.whatsapp.simulateYes}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  color="warning"
                  className="h-7 text-xs"
                  isLoading={isSimulating}
                  onClick={() => handleSimulate('لا، شكرًا', 'invite_no')}
                >
                  {ar.whatsapp.simulateNo}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  color="danger"
                  className="h-7 text-xs"
                  isLoading={isSimulating}
                  onClick={() => handleSimulate('إيقاف')}
                >
                  {ar.whatsapp.simulateStop}
                </Button>
              </div>
            </div>

            {/* Custom simulation input */}
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (customReplyText.trim()) {
                  handleSimulate(customReplyText.trim());
                }
              }}
              className="flex items-center gap-2"
            >
              <Input
                size="sm"
                variant="bordered"
                placeholder={ar.whatsapp.searchPlaceholder}
                value={customReplyText}
                onChange={(e) => setCustomReplyText(e.target.value)}
                disabled={isSimulating}
                className="flex-1"
              />
              <Button
                size="sm"
                color="primary"
                type="submit"
                isIconOnly
                isLoading={isSimulating}
                disabled={!customReplyText.trim()}
              >
                <PaperAirplaneIcon className="w-4 h-4 rtl:-scale-x-100" />
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
