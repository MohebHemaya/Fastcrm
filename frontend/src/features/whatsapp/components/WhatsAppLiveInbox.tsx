
import React, {
  useState,
  useEffect,
  useRef,
  useCallback,
  useMemo,
} from 'react';
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
  TagIcon,
  PaperClipIcon,
  MusicalNoteIcon,
  BellIcon,
  EllipsisVerticalIcon,
  InboxStackIcon,
  UserPlusIcon,
  NoSymbolIcon,
  SignalIcon,
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
import { useSmartInboxPolling } from '@/hooks/useSmartInboxPolling';
import { useWhatsAppInbox } from '@/hooks/useWhatsAppInbox';
import { useDriverChat } from '@/hooks/useDriverChat';

// ─── Conversation Tags ────────────────────────────────────────────────────────
const CONVERSATION_TAGS = [
  { id: 'important', label: '🔴 مهم',         color: 'danger'   as const },
  { id: 'new_join',  label: '🟢 انضمام جديد',  color: 'success'  as const },
  { id: 'complaint', label: '🟡 شكوى',         color: 'warning'  as const },
  { id: 'financial', label: '🔵 مالي',          color: 'primary'  as const },
] as const;
type TagId = (typeof CONVERSATION_TAGS)[number]['id'];

// ─── Audio Chime (Web Audio API) ─────────────────────────────────────────────
function playChime() {
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.4);
  } catch { /* Web Audio not available */ }
}

// ─── Variable substitution for Quick Replies ─────────────────────────────────
function interpolateTemplate(text: string, vars: Record<string, string | number | null | undefined>): string {
  return text.replace(/\{([^}]+)\}/g, (_, key) => {
    const value = vars[key.trim()];
    return value !== undefined && value !== null ? String(value) : `{${key}}`;
  });
}

// ─── Status Ticks ─────────────────────────────────────────────────────────────
const MessageStatusTick: React.FC<{ msg: WhatsAppMessage; onRetry?: () => void }> = ({ msg, onRetry }) => {
  if (msg.direction === 'in') return null;
  switch (msg.status) {
    case 'queued':
      return <Tooltip content="في انتظار الإرسال"><ClockIcon className="w-3.5 h-3.5 text-warning-400 inline-block ms-1" /></Tooltip>;
    case 'sent':
      return <Tooltip content="وصلت لسيرفر واتساب"><CheckIcon className="w-3.5 h-3.5 text-default-400 inline-block ms-1" /></Tooltip>;
    case 'delivered':
      return (
        <Tooltip content="وصلت لهاتف السائق">
          <span className="inline-flex items-center -space-x-1.5 rtl:space-x-reverse ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
            <CheckIcon className="w-3.5 h-3.5 text-default-400" />
          </span>
        </Tooltip>
      );
    case 'read':
      return (
        <Tooltip content="قرأها السائق ✓✓">
          <span className="inline-flex items-center -space-x-1.5 rtl:space-x-reverse ms-1">
            <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
            <CheckIcon className="w-3.5 h-3.5 text-blue-500" />
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
            <button type="button" onClick={onRetry} className="text-[10px] text-danger-500 underline hover:no-underline font-bold">
              إعادة
            </button>
          )}
        </span>
      );
    default:
      return null;
  }
};

// ─── Media Bubble ─────────────────────────────────────────────────────────────
const MediaBubble: React.FC<{ msg: WhatsAppMessage }> = ({ msg }) => {
  const payload = msg.payload ?? {};
  const mediaUrl: string | undefined = payload.media_url ?? payload.url;
  const mediaType: string = payload.media_type ?? payload.type ?? 'unknown';
  const fileName: string = payload.file_name ?? payload.filename ?? 'مرفق';
  if (!mediaUrl) return null;
  if (mediaType.startsWith('image')) {
    return (
      <a href={mediaUrl} target="_blank" rel="noopener noreferrer">
        <img src={mediaUrl} alt={fileName} className="max-w-[220px] rounded-lg border border-default-200 cursor-pointer hover:opacity-90 transition-opacity" loading="lazy" />
      </a>
    );
  }
  if (mediaType === 'audio' || mediaType.startsWith('audio')) {
    return (
      <div className="flex items-center gap-2 bg-default-100 dark:bg-default-800 rounded-xl px-3 py-2 max-w-[220px]">
        <MusicalNoteIcon className="w-5 h-5 text-primary-500 shrink-0" />
        <audio controls className="h-8 max-w-[160px]"><source src={mediaUrl} /></audio>
      </div>
    );
  }
  return (
    <a href={mediaUrl} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-primary-600 underline text-xs">
      <PaperClipIcon className="w-4 h-4 shrink-0" />{fileName}
    </a>
  );
};

// ─── Connection Health Banner ─────────────────────────────────────────────────
const ConnectionHealthBanner: React.FC<{ isConnected: boolean; provider: string; stuckQueued: boolean }> = ({ isConnected, provider, stuckQueued }) => {
  if (isConnected && !stuckQueued) return null;
  return (
    <div className="flex items-center gap-2 bg-danger-50 dark:bg-danger-950/40 border-b border-danger-200 dark:border-danger-800 px-4 py-2">
      <SignalIcon className="w-4 h-4 text-danger-600 animate-pulse shrink-0" />
      <span className="text-xs font-bold text-danger-700 dark:text-danger-400">
        {stuckQueued
          ? '⚠️ رسائل عالقة في الانتظار منذ أكثر من 30 دقيقة. تحقق من حالة الكيو.'
          : `⚠️ انقطع الاتصال بمزود الواتساب (${provider}). قد تحتاج لإعادة مسح QR Code.`}
      </span>
    </div>
  );
};

// ─── Driver Side Drawer ───────────────────────────────────────────────────────
const DriverSideDrawer: React.FC<{
  driverMeta: DriverChatMeta | null;
  conversation: ConversationItem | null;
  tags: TagId[];
  onTagToggle: (tag: TagId) => void;
  onClose: () => void;
}> = ({ driverMeta, conversation, tags, onTagToggle, onClose }) => (
  <div className="flex flex-col h-full bg-content1 border-s border-default-200 w-64 shrink-0 overflow-y-auto p-4 space-y-4">
    <div className="flex items-center justify-between">
      <h5 className="text-sm font-bold text-default-800">ملف السائق</h5>
      <Button isIconOnly size="sm" variant="light" onPress={onClose} aria-label="إغلاق">
        <ChevronLeftIcon className="w-4 h-4 rtl:rotate-180" />
      </Button>
    </div>

    <div className="flex flex-col items-center gap-2 py-2">
      <Avatar name={conversation?.name || driverMeta?.driver_name || 'سائق'} size="lg" className="bg-primary-100 text-primary-700 font-bold" />
      <div className="text-center">
        <p className="text-sm font-bold text-default-900 truncate max-w-[200px]">{conversation?.name || driverMeta?.driver_name || 'كابتن'}</p>
        <p className="text-xs text-default-500 font-mono">{driverMeta?.driver_phone || conversation?.phone}</p>
      </div>
      <Chip size="sm" variant="flat" color={driverMeta?.driver_status === 'active' ? 'success' : 'default'} className="text-[11px]">
        {driverMeta?.driver_status || conversation?.status || '—'}
      </Chip>
    </div>

    <div className="border-t border-default-100 pt-3 space-y-2">
      <p className="text-[11px] font-bold text-default-500 uppercase tracking-wide">تصنيف المحادثة</p>
      <div className="flex flex-wrap gap-1.5">
        {CONVERSATION_TAGS.map((t) => (
          <Chip key={t.id} size="sm" variant={tags.includes(t.id) ? 'solid' : 'flat'} color={t.color} className="cursor-pointer text-[11px] select-none" onClick={() => onTagToggle(t.id)}>
            {t.label}
          </Chip>
        ))}
      </div>
    </div>

    <div className="border-t border-default-100 pt-3 space-y-2">
      <p className="text-[11px] font-bold text-default-500 uppercase tracking-wide">إجراءات سريعة</p>
      <Button size="sm" variant="flat" color="primary" startContent={<UserPlusIcon className="w-3.5 h-3.5" />} className="w-full text-xs justify-start"
        onPress={() => window.open(`/work-orders/new?driver_id=${driverMeta?.driver_id || conversation?.id}`, '_blank')}>
        إنشاء أمر تشغيل
      </Button>
      <Button size="sm" variant="flat" color="default" startContent={<InboxStackIcon className="w-3.5 h-3.5" />} className="w-full text-xs justify-start"
        onPress={() => window.open(`/drivers/${driverMeta?.driver_id || conversation?.id}`, '_blank')}>
        ملف السائق الكامل
      </Button>
      <Button size="sm" variant="flat" color="warning" startContent={<NoSymbolIcon className="w-3.5 h-3.5" />} className="w-full text-xs justify-start"
        as="a" href={`tel:${driverMeta?.driver_phone || conversation?.phone}`}>
        اتصال هاتفي
      </Button>
    </div>
  </div>
);

// ─── Main Component ───────────────────────────────────────────────────────────
interface WhatsAppLiveInboxProps {
  onOpenTemplates: () => void;
}

export const WhatsAppLiveInbox: React.FC<WhatsAppLiveInboxProps> = ({ onOpenTemplates }) => {
  const isMobile = useIsMobile();
  const addToast = useUiStore((state) => state.addToast);

  const [conversations, setConversations]           = useState<ConversationItem[]>([]);
  const [selectedDriverId, setSelectedDriverId]     = useState<number | null>(null);
  const [searchQuery, setSearchQuery]               = useState('');
  const [debouncedSearch, setDebouncedSearch]       = useState('');
  const [isConversationsLoading, setIsConversationsLoading] = useState(true);
  const [filterType, setFilterType]                 = useState<'all' | 'unread' | 'active'>('all');
  const [conversationTags, setConversationTags]     = useState<Record<number, TagId[]>>({});

  const [messages, setMessages]                     = useState<WhatsAppMessage[]>([]);
  const [driverMeta, setDriverMeta]                 = useState<DriverChatMeta | null>(null);
  const [isMessagesLoading, setIsMessagesLoading]   = useState(false);
  const [replyText, setReplyText]                   = useState('');
  const [isSending, setIsSending]                   = useState(false);
  const [retryingId, setRetryingId]                 = useState<number | null>(null);
  const lastMessageIdRef                            = useRef<number>(0);

  const [quickReplies, setQuickReplies]             = useState<QuickReplyItem[]>([]);

  const [connectionHealth, setConnectionHealth]     = useState<{
    isConnected: boolean; provider: string; stuckQueued: boolean;
  } | null>(null);

  const [showMobileChat, setShowMobileChat]         = useState(false);
  const [showDrawer, setShowDrawer]                 = useState(false);

  const messagesEndRef                              = useRef<HTMLDivElement>(null);
  const textareaRef                                 = useRef<HTMLTextAreaElement>(null);
  const originalTitleRef                            = useRef(document.title);

  const scrollToBottom = (smooth = true) =>
    messagesEndRef.current?.scrollIntoView?.({ behavior: smooth ? 'smooth' : 'auto' });

  // Restore tab title on unmount
  useEffect(() => () => { document.title = originalTitleRef.current; }, []);

  // Debounce search
  useEffect(() => {
    const h = setTimeout(() => setDebouncedSearch(searchQuery), 350);
    return () => clearTimeout(h);
  }, [searchQuery]);

  // ── Load conversations (full) ─────────────────────────────────────────────
  const loadConversations = useCallback(async (selectFirst = false) => {
    setIsConversationsLoading(true);
    try {
      const res = await whatsappService.getConversations({ search: debouncedSearch.trim() || undefined });
      const list = res.data || [];
      setConversations(list);
      const totalUnread = list.reduce((s, c) => s + c.unread_count, 0);
      document.title = totalUnread > 0 ? `(${totalUnread}) رسالة جديدة — ${originalTitleRef.current}` : originalTitleRef.current;
      if (selectFirst && list.length > 0 && !selectedDriverId) setSelectedDriverId(list[0].id);
    } catch { /* non-critical */ } finally {
      setIsConversationsLoading(false);
    }
  }, [debouncedSearch, selectedDriverId]);

  // ── Load full message history ─────────────────────────────────────────────
  const loadDriverMessages = useCallback(async (id: number) => {
    setIsMessagesLoading(true);
    try {
      const res = await whatsappService.getDriverMessages(id);
      const msgs = res.data || [];
      setMessages(msgs);
      setDriverMeta(res.meta || null);
      if (msgs.length > 0) lastMessageIdRef.current = msgs[msgs.length - 1].id;
      setTimeout(() => scrollToBottom(false), 50);
    } catch {
      addToast({ title: ar.errors.generalError, message: 'فشل تحميل رسائل السائق', type: 'error' });
    } finally {
      setIsMessagesLoading(false);
    }
  }, [addToast]);

  // ── Incremental poll (new messages + status ticks) ───────────────────────
  const pollMessages = useCallback(async () => {
    if (!selectedDriverId) return;
    try {
      const res = await whatsappService.getNewMessages(selectedDriverId, lastMessageIdRef.current);
      const newMsgs = res.data || [];
      const updatedStatuses = res.meta?.statuses;

      setMessages((prev) => {
        let hasChanges = false;
        let updatedList = prev;

        // 1. Sync delivery & read status ticks on existing outgoing messages (sent -> delivered -> read)
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
          }
        }

        return hasChanges ? updatedList : prev;
      });

      if (newMsgs.length > 0) {
        const freshInbound = newMsgs.filter((m) => m.direction === 'in');
        if (freshInbound.length > 0 && document.hidden) {
          playChime();
          const conv = conversations.find((c) => c.id === selectedDriverId);
          document.title = `(${freshInbound.length}) رسالة جديدة من ${conv?.name || 'سائق'} — ${originalTitleRef.current}`;
        }
        lastMessageIdRef.current = newMsgs[newMsgs.length - 1].id;
        setTimeout(() => scrollToBottom(true), 50);
        // Mark new inbound as read since conversation is open
        whatsappService.markRead(selectedDriverId).catch(() => {});
      }
    } catch { /* silent */ }
  }, [selectedDriverId, conversations]);

  // ── Poll conversations (sidebar update) ───────────────────────────────────
  const pollConversations = useCallback(async () => {
    try {
      const res = await whatsappService.getConversations({ search: debouncedSearch.trim() || undefined });
      const list = res.data || [];
      setConversations(list);
      const totalUnread = list.reduce((s, c) => s + c.unread_count, 0);
      if (totalUnread > 0 && document.hidden) {
        document.title = `(${totalUnread}) رسالة جديدة — ${originalTitleRef.current}`;
        playChime();
      } else if (totalUnread === 0) {
        document.title = originalTitleRef.current;
      }
    } catch { /* silent */ }
  }, [debouncedSearch]);

  // ── Realtime Pusher Subscriptions ─────────────────────────────────────────
  const { isConnected: isPusherInboxConnected } = useWhatsAppInbox({
    onConversationUpdated: (payload) => {
      setConversations((prev) => {
        const index = prev.findIndex((c) => c.id === payload.driver_id);
        if (index !== -1) {
          const updated = [...prev];
          const item = { ...updated[index] };
          item.last_message = {
            id: item.last_message?.id ?? 0,
            body: payload.last_message,
            direction: 'in',
            status: 'delivered',
            created_at: payload.updated_at,
          };
          item.unread_count = payload.unread_count;
          updated.splice(index, 1);
          return [item, ...updated];
        }
        loadConversations(false);
        return prev;
      });
    },
    onMessageReceived: () => {
      playChime();
    },
  });

  const { isConnected: isPusherChatConnected } = useDriverChat({
    driverId: selectedDriverId,
    onNewMessage: (msg) => {
      setMessages((prev) => {
        const exists = prev.some((m) => m.id === msg.id);
        if (exists) return prev.map((m) => (m.id === msg.id ? { ...m, ...msg } : m));
        return [...prev, msg];
      });
      if (msg.id > lastMessageIdRef.current) {
        lastMessageIdRef.current = msg.id;
      }
      setTimeout(() => scrollToBottom(true), 60);
    },
  });

  // ── Smart Polling hook (graceful fallback) ─────────────────────────────────
  const isPusherActive = isPusherInboxConnected || isPusherChatConnected;
  useSmartInboxPolling(
    { onPollMessages: pollMessages, onPollConversations: pollConversations },
    selectedDriverId,
    {
      messageIntervalMs: isPusherActive ? 25000 : 5000,
      conversationIntervalMs: isPusherActive ? 30000 : 8000,
    },
  );

  // ── Connection health (every 2 min) ───────────────────────────────────────
  useEffect(() => {
    const check = async () => {
      try {
        const res = await whatsappService.getConnectionStatus();
        setConnectionHealth({ isConnected: res.data.is_connected, provider: res.data.provider, stuckQueued: res.data.stuck_queued });
      } catch { /* ignore */ }
    };
    check();
    const t = setInterval(check, 120_000);
    return () => clearInterval(t);
  }, []);

  // Initial load
  useEffect(() => { loadConversations(true); }, [loadConversations]);
  useEffect(() => { loadQuickReplies(); }, []);

  const loadQuickReplies = async () => {
    try {
      const res = await whatsappService.getTemplates();
      setQuickReplies(res.data.quick_replies || []);
    } catch { /* fallback */ }
  };

  // Open conversation → full load + mark as seen
  useEffect(() => {
    if (selectedDriverId) {
      lastMessageIdRef.current = 0;
      loadDriverMessages(selectedDriverId);
      whatsappService.markRead(selectedDriverId).catch(() => {});
      setConversations((prev) => prev.map((c) => c.id === selectedDriverId ? { ...c, unread_count: 0 } : c));
      document.title = originalTitleRef.current;
    } else {
      setMessages([]);
      setDriverMeta(null);
    }
  }, [selectedDriverId, loadDriverMessages]);

  const handleSelectDriver = (item: ConversationItem) => {
    setSelectedDriverId(item.id);
    if (isMobile) setShowMobileChat(true);
  };

  const handleSendMessage = async () => {
    if (!selectedDriverId || !replyText.trim() || isSending) return;
    const textToSend = replyText.trim();
    setIsSending(true);
    try {
      const res = await whatsappService.sendDirectMessage(selectedDriverId, textToSend);
      setReplyText('');
      setMessages((prev) => {
        const exists = prev.some((m) => m.id === res.data.id);
        if (exists) return prev;
        if (res.data.id > lastMessageIdRef.current) lastMessageIdRef.current = res.data.id;
        return [...prev, res.data];
      });
      setTimeout(() => scrollToBottom(true), 50);
      pollConversations();
    } catch (e: any) {
      addToast({ title: ar.errors.generalError, message: e?.response?.data?.message || 'فشل إرسال الرسالة', type: 'error' });
    } finally {
      setIsSending(false);
    }
  };

  const handleRetry = async (messageId: number) => {
    setRetryingId(messageId);
    try {
      const res = await whatsappService.retryMessage(messageId);
      setMessages((prev) => prev.map((m) => m.id === messageId ? res.data : m));
      addToast({ title: 'إعادة الإرسال', message: 'تمت جدولة إعادة إرسال الرسالة', type: 'success' });
    } catch {
      addToast({ title: ar.errors.generalError, message: 'فشل إعادة الإرسال', type: 'error' });
    } finally {
      setRetryingId(null);
    }
  };

  const handleInsertQuickReply = (text: string) => {
    const conv = conversations.find((c) => c.id === selectedDriverId);
    const interpolated = interpolateTemplate(text, {
      driver_name: conv?.name || driverMeta?.driver_name || 'كابتن',
      phone: conv?.phone || driverMeta?.driver_phone || '',
    });
    setReplyText((prev) => (prev ? `${prev}\n${interpolated}` : interpolated));
    textareaRef.current?.focus();
  };

  const handleTagToggle = (tag: TagId) => {
    if (!selectedDriverId) return;
    setConversationTags((prev) => {
      const existing = prev[selectedDriverId] || [];
      const updated = existing.includes(tag) ? existing.filter((t) => t !== tag) : [...existing, tag];
      return { ...prev, [selectedDriverId]: updated };
    });
  };

  const selectedConversation = conversations.find((c) => c.id === selectedDriverId);
  const selectedTags = selectedDriverId ? (conversationTags[selectedDriverId] ?? []) : [];

  const filteredConversations = useMemo(() =>
    conversations.filter((c) => {
      if (filterType === 'unread') return c.unread_count > 0;
      if (filterType === 'active') return c.status === 'active';
      return true;
    }), [conversations, filterType]);

  const formatMsgTime = (dateStr: string) => {
    try { return new Date(dateStr).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }); }
    catch { return ''; }
  };
  const formatConversationDate = (dateStr?: string) => {
    if (!dateStr) return '';
    try {
      const d = new Date(dateStr); const now = new Date();
      if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
      return d.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
    } catch { return ''; }
  };

  return (
    <Card className="border border-default-200 shadow-md bg-content1 overflow-hidden">
      {connectionHealth && (
        <ConnectionHealthBanner isConnected={connectionHealth.isConnected} provider={connectionHealth.provider} stuckQueued={connectionHealth.stuckQueued} />
      )}

      <div className="grid grid-cols-1 lg:grid-cols-12 h-[750px] max-h-[85vh] overflow-hidden">

        {/* ── CONVERSATIONS LIST ────────────────────────────────────── */}
        <div className={`lg:col-span-4 xl:col-span-4 border-e border-default-200 flex flex-col h-full overflow-hidden bg-content1 ${isMobile && showMobileChat ? 'hidden' : 'flex'}`}>
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
                  <Button isIconOnly size="sm" variant="light" onPress={() => loadConversations(false)} aria-label="تحديث">
                    <ArrowPathIcon className={`w-4 h-4 ${isConversationsLoading ? 'animate-spin' : ''}`} />
                  </Button>
                </Tooltip>
                <Tooltip content="إدارة القوالب والردود الجاهزة">
                  <Button size="sm" variant="flat" color="primary" startContent={<SparklesIcon className="w-3.5 h-3.5" />} onPress={onOpenTemplates} className="text-xs h-8 font-bold">
                    القوالب
                  </Button>
                </Tooltip>
              </div>
            </div>

            <Input value={searchQuery} onValueChange={setSearchQuery} size="sm" variant="bordered" placeholder="بحث بالاسم أو رقم الهاتف..."
              startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />} isClearable onClear={() => setSearchQuery('')} className="text-xs" />

            <div className="flex items-center gap-1.5 overflow-x-auto pb-0.5">
              {([{ id: 'all', label: 'الكل' }, { id: 'unread', label: 'غير مقروءة' }, { id: 'active', label: 'نشط بالأسطول' }] as const).map((btn) => (
                <Chip key={btn.id} size="sm" variant={filterType === btn.id ? 'solid' : 'flat'} color={filterType === btn.id ? 'primary' : 'default'}
                  className="cursor-pointer text-[11px] font-medium select-none" onClick={() => setFilterType(btn.id)}>
                  {btn.label}
                </Chip>
              ))}
            </div>
          </div>

          <div className="flex-1 min-h-0 overflow-y-auto divide-y divide-default-100">
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
                const tags = conversationTags[item.id] ?? [];
                return (
                  <button key={item.id} type="button" onClick={() => handleSelectDriver(item)}
                    className={`w-full text-start p-3 transition-colors flex items-center gap-3 relative ${isSelected ? 'bg-primary-50/70 dark:bg-primary-950/30 border-s-4 border-primary-500' : 'hover:bg-default-100/70 bg-content1'}`}>
                    <Badge content="" color="success" shape="circle" placement="bottom-right" isInvisible={item.status !== 'active'}>
                      <Avatar name={item.name} size="md" className="bg-default-200 text-default-700 font-bold text-xs" />
                    </Badge>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between mb-0.5">
                        <div className="flex items-center gap-1 min-w-0">
                          <span className={`text-xs font-bold truncate ${isSelected ? 'text-primary-700 dark:text-primary-400' : 'text-default-900'}`}>{item.name}</span>
                          {tags.length > 0 && <TagIcon className="w-3 h-3 text-warning-500 shrink-0" />}
                        </div>
                        <span className="text-[10px] text-default-400 font-mono shrink-0">{formatConversationDate(item.last_message?.created_at)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-1">
                        <p className="text-[11px] text-default-500 truncate font-normal">
                          {item.last_message ? (<>{item.last_message.direction === 'out' && <span className="text-primary-500 me-1">أنت:</span>}{item.last_message.body || '📎 مرفق'}</>) : (<span className="text-default-400 font-mono text-[10px]">{item.phone}</span>)}
                        </p>
                        {item.unread_count > 0 && (
                          <span className="px-1.5 py-0.5 bg-emerald-500 text-white rounded-full text-[10px] font-bold shrink-0">{item.unread_count}</span>
                        )}
                      </div>
                      {tags.length > 0 && (
                        <div className="flex gap-1 mt-1 flex-wrap">
                          {tags.map((t) => {
                            const def = CONVERSATION_TAGS.find((x) => x.id === t);
                            return def ? <span key={t} className="text-[9px] px-1 py-0.5 rounded-full bg-default-100 text-default-600">{def.label}</span> : null;
                          })}
                        </div>
                      )}
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* ── CHAT WINDOW ──────────────────────────────────────────── */}
        <div className={`lg:col-span-8 xl:col-span-8 flex h-full overflow-hidden ${isMobile && !showMobileChat ? 'hidden' : 'flex'}`}>
          {selectedDriverId ? (
            <div className="flex flex-col flex-1 min-w-0 min-h-0 overflow-hidden bg-[#efeae2]/40 dark:bg-[#0b141a]/60">
              {/* Chat Header */}
              <div className="p-3.5 border-b border-default-200 bg-content1 flex items-center justify-between gap-2 shadow-xs z-10">
                <div className="flex items-center gap-3 min-w-0">
                  {isMobile && (
                    <Button isIconOnly size="sm" variant="light" onPress={() => setShowMobileChat(false)} aria-label="الرجوع للقائمة">
                      <ChevronLeftIcon className="w-5 h-5 rtl:rotate-180" />
                    </Button>
                  )}
                  <button type="button" onClick={() => setShowDrawer((v) => !v)} className="flex items-center gap-3 min-w-0 hover:opacity-80 transition-opacity" aria-label="ملف السائق">
                    <Avatar name={selectedConversation?.name || driverMeta?.driver_name || 'سائق'} size="sm" className="bg-primary-100 text-primary-700 font-bold text-xs shrink-0" />
                    <div className="min-w-0 text-start">
                      <div className="flex items-center gap-2">
                        <h4 className="text-sm font-bold text-default-900 truncate">{selectedConversation?.name || driverMeta?.driver_name || 'كابتن'}</h4>
                        <Chip size="sm" variant="flat" color={selectedConversation?.status === 'active' ? 'success' : 'default'} className="text-[10px] h-5">
                          {selectedConversation?.status === 'active' ? 'أسطول نشط' : selectedConversation?.status || 'سائق'}
                        </Chip>
                      </div>
                      <span className="text-xs text-default-500 font-mono">{selectedConversation?.phone || driverMeta?.driver_phone}</span>
                    </div>
                  </button>
                </div>

                <div className="flex items-center gap-1.5 shrink-0">
                  {selectedTags.length > 0 && (
                    <div className="hidden sm:flex gap-1">
                      {selectedTags.map((t) => {
                        const def = CONVERSATION_TAGS.find((x) => x.id === t);
                        return def ? <Chip key={t} size="sm" variant="flat" color={def.color} className="text-[10px] h-5">{def.label}</Chip> : null;
                      })}
                    </div>
                  )}
                  <Tooltip content="ملف السائق والتصنيفات">
                    <Button isIconOnly size="sm" variant={showDrawer ? 'flat' : 'light'} color={showDrawer ? 'primary' : 'default'} onPress={() => setShowDrawer((v) => !v)} aria-label="ملف السائق">
                      <EllipsisVerticalIcon className="w-4 h-4" />
                    </Button>
                  </Tooltip>
                  <Tooltip content="اتصال هاتفي">
                    <Button isIconOnly size="sm" variant="light" as="a" href={`tel:${selectedConversation?.phone || driverMeta?.driver_phone}`} aria-label="اتصال">
                      <PhoneIcon className="w-4 h-4 text-default-600" />
                    </Button>
                  </Tooltip>
                  <Tooltip content="فتح في WhatsApp Web">
                    <Button isIconOnly size="sm" variant="light" color="success" as="a"
                      href={`https://wa.me/${(selectedConversation?.phone || driverMeta?.driver_phone || '').replace(/[^0-9]/g, '')}`}
                      target="_blank" rel="noopener noreferrer" aria-label="فتح واتساب">
                      <ArrowTopRightOnSquareIcon className="w-4 h-4 text-emerald-600" />
                    </Button>
                  </Tooltip>
                  <Tooltip content="تحديث المحادثة">
                    <Button isIconOnly size="sm" variant="light" onPress={() => selectedDriverId && loadDriverMessages(selectedDriverId)} aria-label="تحديث">
                      <ArrowPathIcon className={`w-4 h-4 ${isMessagesLoading ? 'animate-spin' : ''}`} />
                    </Button>
                  </Tooltip>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">
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
                    <p className="text-xs text-default-500 max-w-sm">يمكنك بدء المحادثة مباشرة الآن بكتابة رسالة بالأسفل أو إرسال أحد الردود السريعة الجاهزة.</p>
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isOut = msg.direction === 'out';
                    const hasMedia = msg.payload && (msg.payload.media_url || msg.payload.url || msg.payload.media_type);
                    return (
                      <div key={msg.id} className={`flex flex-col ${isOut ? 'items-start' : 'items-end'}`}>
                        <div className={`max-w-[85%] sm:max-w-[70%] rounded-2xl p-3 shadow-xs text-xs leading-relaxed space-y-1 relative ${
                          isOut ? 'bg-[#d9fdd3] dark:bg-[#005c4b] text-[#111b21] dark:text-[#e9edef] rounded-tr-none' : 'bg-content1 text-default-900 border border-default-200 rounded-tl-none'
                        }`}>
                          {msg.template_name && (
                            <div className="text-[10px] text-default-400 font-mono mb-1 pb-1 border-b border-default-200/50">📋 قالب آلي: {msg.template_name}</div>
                          )}
                          {hasMedia && <MediaBubble msg={msg} />}
                          <div className="whitespace-pre-wrap">
                            {msg.body ||
                              (hasMedia ? '' : (
                                <span className="italic text-default-400">(رسالة واردة)</span>
                              ))}
                          </div>
                          <div className="flex items-center justify-end gap-1 text-[10px] text-default-400 mt-1 font-mono select-none">
                            <span>{formatMsgTime(msg.created_at)}</span>
                            <MessageStatusTick msg={msg} onRetry={msg.status === 'failed' && retryingId !== msg.id ? () => handleRetry(msg.id) : undefined} />
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Quick Replies */}
              {quickReplies.length > 0 && (
                <div className="px-3 py-2 bg-content1 border-t border-default-100 flex items-center gap-1.5 overflow-x-auto">
                  <span className="text-[11px] font-bold text-default-400 shrink-0 ms-1">ردود سريعة:</span>
                  {quickReplies.map((reply) => (
                    <Chip key={reply.id} size="sm" variant="flat" color="secondary"
                      className="cursor-pointer hover:bg-secondary-100 transition-colors text-[11px] shrink-0 select-none"
                      onClick={() => handleInsertQuickReply(reply.text)}>
                      {reply.title}
                    </Chip>
                  ))}
                </div>
              )}

              {/* Composer */}
              <div className="p-3 bg-content1 border-t border-default-200 space-y-2">
                <div className="flex items-end gap-2">
                  <Textarea ref={textareaRef} value={replyText} onValueChange={setReplyText} minRows={1} maxRows={4} size="sm" variant="bordered"
                    placeholder="اكتب ردك للسائق هنا... (اضغط Enter للإرسال، Shift+Enter لسطر جديد)"
                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(); } }}
                    className="flex-1 text-xs" />
                  <Button color="primary" isIconOnly className="h-10 w-10 min-w-10 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs"
                    onPress={handleSendMessage} isLoading={isSending} isDisabled={!replyText.trim() || isSending} aria-label="إرسال">
                    <PaperAirplaneIcon className="w-4 h-4 rtl:rotate-180" />
                  </Button>
                </div>
              </div>
            </div>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-8 text-center space-y-3 bg-[#efeae2]/40 dark:bg-[#0b141a]/60">
              <div className="w-16 h-16 rounded-full bg-default-100 text-default-400 flex items-center justify-center">
                <ChatBubbleLeftRightIcon className="w-8 h-8" />
              </div>
              <h4 className="text-base font-bold text-default-800">مركز المحادثات المباشرة مع السائقين</h4>
              <p className="text-xs text-default-500 max-w-md leading-relaxed">
                اختر أي سائق من القائمة على اليمين لفتح محادثته، الاطلاع على رسائله السابقة، والرد عليه فوراً عبر الواتساب دون الحاجة لفتح هاتفك المحمول.
              </p>
              <div className="flex items-center gap-2 text-[11px] text-default-400 bg-default-50 rounded-xl px-4 py-2">
                <BellIcon className="w-3.5 h-3.5" />
                <span>سيتم إشعارك صوتياً وبشكل مرئي عند وصول رسائل جديدة</span>
              </div>
            </div>
          )}

          {/* Side Drawer */}
          {showDrawer && selectedDriverId && (
            <DriverSideDrawer driverMeta={driverMeta} conversation={selectedConversation ?? null}
              tags={selectedTags} onTagToggle={handleTagToggle} onClose={() => setShowDrawer(false)} />
          )}
        </div>
      </div>
    </Card>
  );
};

export default WhatsAppLiveInbox;

