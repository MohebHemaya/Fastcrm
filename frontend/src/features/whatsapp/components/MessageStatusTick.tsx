import React from 'react';
import { Tooltip } from '@heroui/react';
import {
  CheckIcon,
  ClockIcon,
  ExclamationCircleIcon,
  ArrowPathIcon,
} from '@/components/icons';
import type { WhatsAppMessage, WhatsAppStatus } from '@/types/whatsapp';

interface MessageStatusTickProps {
  msg: WhatsAppMessage;
  onRetry?: () => void;
  isRetrying?: boolean;
}

export const MessageStatusTick: React.FC<MessageStatusTickProps> = ({
  msg,
  onRetry,
  isRetrying = false,
}) => {
  if (msg.direction === 'in') return null;

  switch (msg.status) {
    case 'queued':
      return (
        <Tooltip content="في قائمة الانتظار للإرسال">
          <ClockIcon className="w-3.5 h-3.5 text-warning-400 inline-block ms-1" />
        </Tooltip>
      );
    case 'sent':
      return (
        <Tooltip content="تم الإرسال لسيرفر واتساب (✓)">
          <CheckIcon className="w-3.5 h-3.5 text-default-400 inline-block ms-1" />
        </Tooltip>
      );
    case 'delivered':
      return (
        <Tooltip content={msg.delivered_at ? `استلمها السائق: ${new Date(msg.delivered_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })} (✓✓)` : 'استلمها السائق (✓✓)'}>
          <span className="inline-flex items-center -space-x-1.5 rtl:space-x-reverse ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
          </span>
        </Tooltip>
      );
    case 'read':
      return (
        <Tooltip content={msg.read_at ? `قرأها السائق: ${new Date(msg.read_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })} (✓✓ مقروءة)` : 'قرأها السائق (✓✓ مقروءة)'}>
          <span className="inline-flex items-center -space-x-1.5 rtl:space-x-reverse ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-sky-500 font-bold" />
            <CheckIcon className="w-3.5 h-3.5 text-sky-500 font-bold" />
          </span>
        </Tooltip>
      );
    case 'failed':
      return (
        <span className="inline-flex items-center gap-1 ms-1">
          <Tooltip content={msg.error_message || 'فشل الإرسال'}>
            <ExclamationCircleIcon className="w-3.5 h-3.5 text-danger-500" />
          </Tooltip>
          {onRetry && (
            <button
              type="button"
              onClick={onRetry}
              disabled={isRetrying}
              className="text-[10px] text-danger-500 underline hover:no-underline font-bold inline-flex items-center gap-0.5"
              title="إعادة المحاولة"
            >
              <ArrowPathIcon className={`w-3 h-3 ${isRetrying ? 'animate-spin' : ''}`} />
              <span>إعادة</span>
            </button>
          )}
        </span>
      );
    default:
      return null;
  }
};

export const ConversationListTick: React.FC<{ status: WhatsAppStatus }> = ({ status }) => {
  switch (status) {
    case 'queued':
      return (
        <Tooltip content="في الانتظار">
          <ClockIcon className="w-3 h-3 text-warning-400 inline-block me-1 shrink-0" />
        </Tooltip>
      );
    case 'sent':
      return (
        <Tooltip content="أُرسلت">
          <CheckIcon className="w-3 h-3 text-default-400 inline-block me-1 shrink-0" />
        </Tooltip>
      );
    case 'delivered':
      return (
        <Tooltip content="استُلمت">
          <span className="inline-flex items-center -space-x-1 rtl:space-x-reverse me-1 shrink-0">
            <CheckIcon className="w-3 h-3 text-default-400" />
            <CheckIcon className="w-3 h-3 text-default-400" />
          </span>
        </Tooltip>
      );
    case 'read':
      return (
        <Tooltip content="مقروءة">
          <span className="inline-flex items-center -space-x-1 rtl:space-x-reverse me-1 shrink-0">
            <CheckIcon className="w-3 h-3 text-sky-500 font-bold" />
            <CheckIcon className="w-3 h-3 text-sky-500 font-bold" />
          </span>
        </Tooltip>
      );
    case 'failed':
      return (
        <Tooltip content="تعذر الإرسال">
          <ExclamationCircleIcon className="w-3 h-3 text-danger-500 inline-block me-1 shrink-0" />
        </Tooltip>
      );
    default:
      return null;
  }
};

export default MessageStatusTick;
