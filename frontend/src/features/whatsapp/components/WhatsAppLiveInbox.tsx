import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
  Card,
  Button,
  Input,
  Textarea,
  Avatar,
  Badge,
  Chip,
  Spinner,
  Tooltip,
} from '@heroui/react';
import {
  PaperAirplaneIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  PhoneIcon,
  ArrowTopRightOnSquareIcon,
  CheckIcon,
  ClockIcon,
  ExclamationCircleIcon,
  ChatBubbleLeftRightIcon,
  SparklesIcon,
  ChevronLeftIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type {
  ConversationItem,
  WhatsAppMessage,
  QuickReplyItem,
  DriverChatMeta,
} from '@/types/whatsapp';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface WhatsAppLiveInboxProps {
  onOpenTemplates: () => void;
}

export const WhatsAppLiveInbox: React.FC<WhatsAppLiveInboxProps> = ({ onOpenTemplates }) => {
  const isMobile = useIsMobile();
  const addToast = useUiStore((state) => state.addToast);

  // Conversations state
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [selectedDriverId, setSelectedDriverId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [isConversationsLoading, setIsConversationsLoading] = useState(true);
  const [filterType, setFilterType] = useState<'all' | 'unread' | 'active'>('all');

  // Messages state
  const [messages, setMessages] = useState<WhatsAppMessage[]>([]);
  const [driverMeta, setDriverMeta] = useState<DriverChatMeta | null>(null);
  const [isMessagesLoading, setIsMessagesLoading] = useState(false);
  const [replyText, setReplyText] = useState('');
  const [isSending, setIsSending] = useState(false);

  // Quick replies
  const [quickReplies, setQuickReplies] = useState<QuickReplyItem[]>([]);

  // Mobile navigation
  const [showMobileChat, setShowMobileChat] = useState(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const scrollToBottom = (smooth = true) => {
    messagesEndRef.current?.scrollIntoView?.({ behavior: smooth ? 'smooth' : 'auto' });
  };

  // Debounce search
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(searchQuery);
    }, 350);
    return () => clearTimeout(handler);
  }, [searchQuery]);

  // Load conversations
  const loadConversations = useCallback(async (selectFirst = false) => {
    setIsConversationsLoading(true);
    try {
      const res = await whatsappService.getConversations({
        search: debouncedSearch.trim() || undefined,
      });
      const list = res.data || [];
      setConversations(list);

      if (selectFirst && list.length > 0 && !selectedDriverId) {
        setSelectedDriverId(list[0].id);
      }
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: 'فشل تحميل المحادثات',
        type: 'error',
      });
    } finally {
      setIsConversationsLoading(false);
    }
  }, [debouncedSearch, selectedDriverId, addToast]);

  // Load quick replies
  const loadQuickReplies = async () => {
    try {
      const res = await whatsappService.getTemplates();
      setQuickReplies(res.data.quick_replies || []);
    } catch {
      // Fallback defaults
    }
  };

  useEffect(() => {
    loadConversations(true);
    loadQuickReplies();
  }, [loadConversations]);

  // Load messages for selected driver
  const loadDriverMessages = useCallback(async (id: number) => {
    setIsMessagesLoading(true);
    try {
      const res = await whatsappService.getDriverMessages(id);
      setMessages(res.data || []);
      setDriverMeta(res.meta || null);
      setTimeout(() => scrollToBottom(false), 50);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: 'فشل تحميل رسائل السائق',
        type: 'error',
      });
    } finally {
      setIsMessagesLoading(false);
    }
  }, [addToast]);

  useEffect(() => {
    if (selectedDriverId) {
      loadDriverMessages(selectedDriverId);
    } else {
      setMessages([]);
      setDriverMeta(null);
    }
  }, [selectedDriverId, loadDriverMessages]);

  const handleSelectDriver = (item: ConversationItem) => {
    setSelectedDriverId(item.id);
    if (isMobile) {
      setShowMobileChat(true);
    }
  };

  const handleSendMessage = async () => {
    if (!selectedDriverId || !replyText.trim() || isSending) return;

    const textToSend = replyText.trim();
    setIsSending(true);

    try {
      const res = await whatsappService.sendDirectMessage(selectedDriverId, textToSend);
      setReplyText('');

      // Optimistic append
      setMessages((prev) => [...prev, res.data]);
      setTimeout(() => scrollToBottom(true), 50);

      addToast({
        title: 'تم الإرسال',
        message: 'تم إرسال الرسالة إلى السائق عبر الواتساب',
        type: 'success',
      });

      // Refresh conversations list to update snippet
      loadConversations(false);
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

  const handleInsertQuickReply = (text: string) => {
    setReplyText((prev) => (prev ? `${prev}\n${text}` : text));
    textareaRef.current?.focus();
  };

  const selectedConversation = conversations.find((c) => c.id === selectedDriverId);

  // Filtered conversations
  const filteredConversations = conversations.filter((c) => {
    if (filterType === 'unread') return c.unread_count > 0;
    if (filterType === 'active') return c.status === 'active';
    return true;
  });

  const renderStatusTicks = (msg: WhatsAppMessage) => {
    if (msg.direction === 'in') return null;

    switch (msg.status) {
      case 'queued':
        return <ClockIcon className="w-3.5 h-3.5 text-warning-500 inline-block ms-1" />;
      case 'sent':
        return <CheckIcon className="w-3.5 h-3.5 text-default-400 inline-block ms-1" />;
      case 'delivered':
        return (
          <div className="inline-flex -space-x-1.5 ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
          </div>
        );
      case 'read':
        return (
          <div className="inline-flex -space-x-1.5 ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
            <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
          </div>
        );
      case 'failed':
        return (
          <Tooltip content={msg.error_message || 'فشل الإرسال'}>
            <ExclamationCircleIcon className="w-3.5 h-3.5 text-danger-500 inline-block ms-1" />
          </Tooltip>
        );
      default:
        return null;
    }
  };

  const formatMsgTime = (dateStr: string) => {
    try {
      const d = new Date(dateStr);
      return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } catch {
      return '';
    }
  };

  const formatConversationDate = (dateStr?: string) => {
    if (!dateStr) return '';
    try {
      const d = new Date(dateStr);
      const now = new Date();
      if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
      }
      return d.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
    } catch {
      return '';
    }
  };

  return (
    <Card className="border border-default-200 shadow-md bg-content1 overflow-hidden">
      <div className="grid grid-cols-1 lg:grid-cols-12 h-[750px] max-h-[85vh]">
        {/* RIGHT COLUMN: DRIVERS CONVERSATIONS LIST (360px on desktop) */}
        <div
          className={`lg:col-span-4 xl:col-span-4 border-e border-default-200 flex flex-col h-full bg-content1 ${
            isMobile && showMobileChat ? 'hidden' : 'flex'
          }`}
        >
          {/* List Header */}
          <div className="p-3.5 border-b border-default-200 space-y-3 bg-default-50/50">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                  <ChatBubbleLeftRightIcon className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-default-900">محادثات السائقين</h3>
                  <span className="text-[11px] text-default-500">{conversations.length} سائق لديهم رسائل</span>
                </div>
              </div>

              <div className="flex items-center gap-1">
                <Tooltip content="تحديث المحادثات">
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    onPress={() => loadConversations(false)}
                    aria-label="تحديث"
                  >
                    <ArrowPathIcon className={`w-4 h-4 ${isConversationsLoading ? 'animate-spin' : ''}`} />
                  </Button>
                </Tooltip>
                <Tooltip content="إدارة القوالب والردود الجاهزة">
                  <Button
                    size="sm"
                    variant="flat"
                    color="primary"
                    startContent={<SparklesIcon className="w-3.5 h-3.5" />}
                    onPress={onOpenTemplates}
                    className="text-xs h-8 font-bold"
                  >
                    القوالب
                  </Button>
                </Tooltip>
              </div>
            </div>

            {/* Search Input */}
            <Input
              value={searchQuery}
              onValueChange={setSearchQuery}
              size="sm"
              variant="bordered"
              placeholder="بحث بالاسم أو رقم الهاتف..."
              startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />}
              isClearable
              onClear={() => setSearchQuery('')}
              className="text-xs"
            />

            {/* Filter Chips */}
            <div className="flex items-center gap-1.5 overflow-x-auto pb-0.5">
              {[
                { id: 'all', label: 'الكل' },
                { id: 'unread', label: 'غير مقروءة' },
                { id: 'active', label: 'نشط بالأسطول' },
              ].map((btn) => (
                <Chip
                  key={btn.id}
                  size="sm"
                  variant={filterType === btn.id ? 'solid' : 'flat'}
                  color={filterType === btn.id ? 'primary' : 'default'}
                  className="cursor-pointer text-[11px] font-medium select-none"
                  onClick={() => setFilterType(btn.id as any)}
                >
                  {btn.label}
                </Chip>
              ))}
            </div>
          </div>

          {/* Conversations Scrollable List */}
          <div className="flex-1 overflow-y-auto divide-y divide-default-100">
            {isConversationsLoading ? (
              <div className="py-20 flex flex-col items-center justify-center gap-2">
                <Spinner size="sm" color="primary" />
                <span className="text-xs text-default-400">جاري تحميل السائقين...</span>
              </div>
            ) : filteredConversations.length === 0 ? (
              <div className="py-20 text-center px-4 space-y-2">
                <p className="text-xs font-bold text-default-600">لا توجد محادثات مطابقة</p>
                <p className="text-[11px] text-default-400">جرب البحث برقم هاتف أو اسم آخر</p>
              </div>
            ) : (
              filteredConversations.map((item) => {
                const isSelected = item.id === selectedDriverId;
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => handleSelectDriver(item)}
                    className={`w-full text-start p-3 transition-colors flex items-center gap-3 relative ${
                      isSelected
                        ? 'bg-primary-50/70 dark:bg-primary-950/30 border-s-4 border-primary-500'
                        : 'hover:bg-default-100/70 bg-content1'
                    }`}
                  >
                    <Badge
                      content=""
                      color="success"
                      shape="circle"
                      placement="bottom-right"
                      isInvisible={item.status !== 'active'}
                    >
                      <Avatar
                        name={item.name}
                        size="md"
                        className="bg-default-200 text-default-700 font-bold text-xs"
                      />
                    </Badge>

                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between mb-0.5">
                        <span className={`text-xs font-bold truncate ${isSelected ? 'text-primary-700 dark:text-primary-400' : 'text-default-900'}`}>
                          {item.name}
                        </span>
                        <span className="text-[10px] text-default-400 font-mono">
                          {formatConversationDate(item.last_message?.created_at)}
                        </span>
                      </div>

                      <div className="flex items-center justify-between gap-1">
                        <p className="text-[11px] text-default-500 truncate font-normal">
                          {item.last_message ? (
                            <>
                              {item.last_message.direction === 'out' && (
                                <span className="text-primary-500 me-1">أنت:</span>
                              )}
                              {item.last_message.body}
                            </>
                          ) : (
                            <span className="text-default-400 font-mono text-[10px]">{item.phone}</span>
                          )}
                        </p>

                        {item.unread_count > 0 && (
                          <span className="px-1.5 py-0.5 bg-emerald-500 text-white rounded-full text-[10px] font-bold shrink-0">
                            {item.unread_count}
                          </span>
                        )}
                      </div>
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* LEFT COLUMN: CHAT CONVERSATION WINDOW */}
        <div
          className={`lg:col-span-8 xl:col-span-8 flex flex-col h-full bg-[#efeae2]/40 dark:bg-[#0b141a]/60 ${
            isMobile && !showMobileChat ? 'hidden' : 'flex'
          }`}
        >
          {selectedDriverId ? (
            <>
              {/* Chat Header */}
              <div className="p-3.5 border-b border-default-200 bg-content1 flex items-center justify-between gap-2 shadow-xs z-10">
                <div className="flex items-center gap-3 min-w-0">
                  {isMobile && (
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      onPress={() => setShowMobileChat(false)}
                      aria-label="الرجوع للقائمة"
                    >
                      <ChevronLeftIcon className="w-5 h-5 rtl:rotate-180" />
                    </Button>
                  )}

                  <Avatar
                    name={selectedConversation?.name || driverMeta?.driver_name || 'سائق'}
                    size="sm"
                    className="bg-primary-100 text-primary-700 font-bold text-xs shrink-0"
                  />

                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <h4 className="text-sm font-bold text-default-900 truncate">
                        {selectedConversation?.name || driverMeta?.driver_name || 'كابتن'}
                      </h4>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={selectedConversation?.status === 'active' ? 'success' : 'default'}
                        className="text-[10px] h-5"
                      >
                        {selectedConversation?.status === 'active' ? 'أسطول نشط' : selectedConversation?.status || 'سائق'}
                      </Chip>
                    </div>
                    <span className="text-xs text-default-500 font-mono">
                      {selectedConversation?.phone || driverMeta?.driver_phone}
                    </span>
                  </div>
                </div>

                {/* Direct Actions */}
                <div className="flex items-center gap-1.5 shrink-0">
                  <Tooltip content="اتصال هاتفي">
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      as="a"
                      href={`tel:${selectedConversation?.phone || driverMeta?.driver_phone}`}
                      aria-label="اتصال"
                    >
                      <PhoneIcon className="w-4 h-4 text-default-600" />
                    </Button>
                  </Tooltip>

                  <Tooltip content="فتح في WhatsApp Web الخارجي">
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      color="success"
                      as="a"
                      href={`https://wa.me/${(selectedConversation?.phone || driverMeta?.driver_phone || '').replace(/[^0-9]/g, '')}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label="فتح واتساب"
                    >
                      <ArrowTopRightOnSquareIcon className="w-4 h-4 text-emerald-600" />
                    </Button>
                  </Tooltip>

                  <Tooltip content="تحديث المحادثة">
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      onPress={() => loadDriverMessages(selectedDriverId)}
                      aria-label="تحديث"
                    >
                      <ArrowPathIcon className={`w-4 h-4 ${isMessagesLoading ? 'animate-spin' : ''}`} />
                    </Button>
                  </Tooltip>
                </div>
              </div>

              {/* Chat Messages Feed */}
              <div className="flex-1 overflow-y-auto p-4 space-y-3">
                {isMessagesLoading ? (
                  <div className="h-full flex flex-col items-center justify-center gap-2">
                    <Spinner size="md" color="primary" />
                    <span className="text-xs text-default-500">جاري تحميل سجل المحادثة...</span>
                  </div>
                ) : messages.length === 0 ? (
                  <div className="h-full flex flex-col items-center justify-center text-center p-6 space-y-2">
                    <div className="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-1">
                      <ChatBubbleLeftRightIcon className="w-6 h-6" />
                    </div>
                    <h5 className="text-sm font-bold text-default-800">لا توجد رسائل سابقة مع هذا السائق</h5>
                    <p className="text-xs text-default-500 max-w-sm">
                      يمكنك بدء المحادثة مباشرة الآن بكتابة رسالة بالأسفل أو إرسال أحد الردود السريعة الجاهزة.
                    </p>
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isOut = msg.direction === 'out';
                    return (
                      <div
                        key={msg.id}
                        className={`flex flex-col ${isOut ? 'items-start' : 'items-end'}`}
                      >
                        <div
                          className={`max-w-[85%] sm:max-w-[70%] rounded-2xl p-3 shadow-xs text-xs leading-relaxed space-y-1 relative ${
                            isOut
                              ? 'bg-[#d9fdd3] dark:bg-[#005c4b] text-[#111b21] dark:text-[#e9edef] rounded-tr-none'
                              : 'bg-content1 text-default-900 border border-default-200 rounded-tl-none'
                          }`}
                        >
                          {/* Template label if automated */}
                          {msg.template_name && (
                            <div className="text-[10px] text-default-400 font-mono mb-1 pb-1 border-b border-default-200/50">
                              📋 قالب آلي: {msg.template_name}
                            </div>
                          )}

                          <div className="whitespace-pre-wrap">{msg.body}</div>

                          <div className="flex items-center justify-end gap-1 text-[10px] text-default-400 mt-1 font-mono select-none">
                            <span>{formatMsgTime(msg.created_at)}</span>
                            {renderStatusTicks(msg)}
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Quick Replies Strip */}
              {quickReplies.length > 0 && (
                <div className="px-3 py-2 bg-content1 border-t border-default-100 flex items-center gap-1.5 overflow-x-auto">
                  <span className="text-[11px] font-bold text-default-400 shrink-0 ms-1">
                    ردود سريعة:
                  </span>
                  {quickReplies.map((reply) => (
                    <Chip
                      key={reply.id}
                      size="sm"
                      variant="flat"
                      color="secondary"
                      className="cursor-pointer hover:bg-secondary-100 transition-colors text-[11px] shrink-0 select-none"
                      onClick={() => handleInsertQuickReply(reply.text)}
                    >
                      {reply.title}
                    </Chip>
                  ))}
                </div>
              )}

              {/* Chat Composer Bar */}
              <div className="p-3 bg-content1 border-t border-default-200 space-y-2">
                <div className="flex items-end gap-2">
                  <Textarea
                    ref={textareaRef}
                    value={replyText}
                    onValueChange={setReplyText}
                    minRows={1}
                    maxRows={4}
                    size="sm"
                    variant="bordered"
                    placeholder="اكتب ردك للسائق هنا... (اضغط Enter للإرسال، Shift+Enter لسطر جديد)"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        handleSendMessage();
                      }
                    }}
                    className="flex-1 text-xs"
                  />

                  <Button
                    color="primary"
                    isIconOnly
                    className="h-10 w-10 min-w-10 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs"
                    onPress={handleSendMessage}
                    isLoading={isSending}
                    isDisabled={!replyText.trim() || isSending}
                    aria-label="إرسال"
                  >
                    <PaperAirplaneIcon className="w-4 h-4 rtl:rotate-180" />
                  </Button>
                </div>
              </div>
            </>
          ) : (
            <div className="h-full flex flex-col items-center justify-center p-8 text-center space-y-3">
              <div className="w-16 h-16 rounded-full bg-default-100 text-default-400 flex items-center justify-center">
                <ChatBubbleLeftRightIcon className="w-8 h-8" />
              </div>
              <h4 className="text-base font-bold text-default-800">مركز المحادثات المباشرة مع السائقين</h4>
              <p className="text-xs text-default-500 max-w-md leading-relaxed">
                اختر أي سائق من القائمة على اليمين لفتح محادثته، الاطلاع على رسائله السابقة، والرد عليه فوراً عبر الواتساب دون الحاجة لفتح هاتفك المحمول.
              </p>
            </div>
          )}
        </div>
      </div>
    </Card>
  );
};

export default WhatsAppLiveInbox;
