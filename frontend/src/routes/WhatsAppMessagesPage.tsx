import React, { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  Button,
  Tabs,
  Tab,
  Chip,
} from '@heroui/react';
import {
  ArrowPathIcon,
  ChatBubbleLeftRightIcon,
  SignalIcon,
  ArrowTopRightOnSquareIcon,
  DocumentTextIcon,
  QueueListIcon,
} from '@/components/icons';
import { SearchInput } from '@/components/form';
import PageContainer from '@/components/layout/PageContainer';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type {
  WhatsAppMessage,
  WhatsAppStatusCounts,
  WhatsAppProviderStatus,
} from '@/types/whatsapp';
import WhatsAppStatusBadge from '@/features/whatsapp/components/WhatsAppStatusBadge';
import DriverChatModal from '@/features/whatsapp/components/DriverChatModal';
import { WhatsAppLiveInbox } from '@/features/whatsapp/components/WhatsAppLiveInbox';
import { WhatsAppTemplatesModal } from '@/features/whatsapp/components/WhatsAppTemplatesModal';

export const WhatsAppMessagesPage: React.FC = () => {
  const [pageMode, setPageMode] = useState<'inbox' | 'logs'>('inbox');
  const [isTemplatesModalOpen, setIsTemplatesModalOpen] = useState(false);
  const [messages, setMessages] = useState<WhatsAppMessage[]>([]);
  const [counts, setCounts] = useState<WhatsAppStatusCounts>({
    total: 0,
    queued: 0,
    sent: 0,
    delivered: 0,
    read: 0,
    failed: 0,
  });
  const [providerStatus, setProviderStatus] = useState<WhatsAppProviderStatus | null>(null);

  const [activeTab, setActiveTab] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  const [retryingId, setRetryingId] = useState<number | null>(null);

  // Chat modal state
  const [selectedDriver, setSelectedDriver] = useState<{
    id: number;
    name?: string | null;
    phone: string;
  } | null>(null);

  const addToast = useUiStore((state) => state.addToast);

  // Debounce search
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearch(searchQuery);
      setCurrentPage(1);
    }, 400);
    return () => clearTimeout(handler);
  }, [searchQuery]);

  const loadProviderStatus = async () => {
    try {
      const res = await whatsappService.getProviderStatus();
      setProviderStatus(res.data);
    } catch {
      // Non-critical, ignore
    }
  };

  const loadMessages = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await whatsappService.getMessages({
        page: currentPage,
        status: activeTab === 'all' ? undefined : activeTab,
        search: debouncedSearch.trim() || undefined,
      });

      setMessages(res.data);
      setTotalPages(res.meta.last_page);
      if (res.meta.counts) {
        setCounts(res.meta.counts);
      }
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, activeTab, debouncedSearch, addToast]);

  useEffect(() => {
    loadProviderStatus();
  }, []);

  useEffect(() => {
    loadMessages();
    const interval = setInterval(() => {
      loadMessages();
    }, 4000);
    return () => clearInterval(interval);
  }, [loadMessages]);

  const handleRetry = async (messageId: number) => {
    setRetryingId(messageId);
    try {
      await whatsappService.retryMessage(messageId);
      addToast({
        title: ar.whatsapp.retrySuccess,
        message: ar.whatsapp.retrySuccess,
        type: 'success',
      });
      loadMessages();
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setRetryingId(null);
    }
  };

  const formatDateTime = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('ar-EG', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const columns: ColumnDef<WhatsAppMessage>[] = [
    {
      id: 'driver_name',
      header: ar.drivers.name,
      isPrimary: true,
      render: (msg) => (
        <span className="font-semibold text-default-900">
          {msg.driver_name || ar.drivers.unnamed}
        </span>
      ),
    },
    {
      id: 'driver_phone',
      header: ar.drivers.phone,
      render: (msg) => (
        <span className="font-mono text-xs text-default-700" dir="ltr">
          {msg.driver_phone}
        </span>
      ),
    },
    {
      id: 'status',
      header: ar.common.status,
      render: (msg) => <WhatsAppStatusBadge status={msg.status} />,
    },
    {
      id: 'direction',
      header: 'الاتجاه',
      render: (msg) => (
        <Chip
          size="sm"
          variant="flat"
          color={msg.direction === 'out' ? 'primary' : 'secondary'}
          className="text-xs"
        >
          {msg.direction_label}
        </Chip>
      ),
    },
    {
      id: 'body',
      header: 'النص / القالب',
      render: (msg) => (
        <div className="max-w-xs space-y-0.5">
          {msg.template_name && (
            <span className="inline-block text-[11px] font-semibold bg-default-100 text-default-600 px-2 py-0.5 rounded me-1">
              {msg.template_name}
            </span>
          )}
          <span className="truncate block text-default-700 text-xs">
            {msg.body || '—'}
          </span>
          {msg.error_message && (
            <span className="block text-[11px] text-danger-500 truncate" title={msg.error_message}>
              {msg.error_message}
            </span>
          )}
        </div>
      ),
    },
    {
      id: 'created_at',
      header: 'التاريخ',
      render: (msg) => (
        <span className="text-xs text-default-500 whitespace-nowrap">
          {formatDateTime(msg.created_at)}
        </span>
      ),
    },
    {
      id: 'actions',
      header: ar.common.actions,
      className: 'text-end',
      headerClassName: 'text-end',
      render: (msg) => {
        const isOutbound = msg.direction === 'out';
        return (
          <div className="flex items-center justify-end gap-1.5">
            {msg.status === 'failed' && isOutbound && (
              <Button
                size="sm"
                variant="flat"
                color="danger"
                className="min-h-[36px] text-xs"
                startContent={<ArrowPathIcon className="w-3.5 h-3.5" />}
                isLoading={retryingId === msg.id}
                onPress={() => handleRetry(msg.id)}
              >
                {ar.whatsapp.retry}
              </Button>
            )}
            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="primary"
              className="min-w-[40px] min-h-[40px]"
              title={ar.whatsapp.driverChat}
              onPress={() =>
                setSelectedDriver({
                  id: msg.driver_id,
                  name: msg.driver_name,
                  phone: msg.driver_phone || '',
                })
              }
            >
              <ArrowTopRightOnSquareIcon className="w-4 h-4 rtl:-scale-x-100" />
            </Button>
          </div>
        );
      },
    },
  ];

  return (
    <PageContainer className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-default-900 flex items-center gap-2.5">
            <ChatBubbleLeftRightIcon className="w-7 h-7 text-emerald-600" />
            {ar.whatsapp.title}
          </h1>
          <p className="text-sm text-default-500 mt-1">{ar.whatsapp.subtitle}</p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {/* Templates Manager Button */}
          <Button
            size="sm"
            color="primary"
            variant="flat"
            className="font-bold text-xs min-h-[38px]"
            startContent={<DocumentTextIcon className="w-4 h-4 text-primary" />}
            onPress={() => setIsTemplatesModalOpen(true)}
          >
            صيغ وقوالب الرسائل
          </Button>

          {/* Provider Status Pill */}
          {providerStatus && (
            <Chip
              variant="flat"
              color="success"
              startContent={<SignalIcon className="w-3.5 h-3.5 text-success-500" />}
              className="text-xs font-semibold px-3 py-1.5"
            >
              {providerStatus.provider === 'ultramsg'
                ? ar.whatsapp.ultramsgProvider
                : providerStatus.provider === 'cloud'
                ? ar.whatsapp.cloudProvider
                : 'مزود واتساب'}
            </Chip>
          )}

          <Button
            isIconOnly
            size="sm"
            variant="flat"
            className="min-w-[38px] min-h-[38px]"
            onPress={loadMessages}
            title={ar.common.refresh}
            isLoading={isLoading}
          >
            <ArrowPathIcon className="w-4 h-4" />
          </Button>
        </div>
      </div>

      {/* Primary View Switcher: Live Chat Inbox vs Logs Table */}
      <div className="flex items-center justify-between gap-4">
        <Tabs
          selectedKey={pageMode}
          onSelectionChange={(key) => setPageMode(key as any)}
          color="primary"
          variant="solid"
          classNames={{
            tabList: 'bg-default-100 p-1 rounded-xl gap-1 border border-default-200/50 shadow-xs',
            tab: 'data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground font-bold text-xs px-4 py-2 rounded-lg transition-all',
          }}
        >
          <Tab
            key="inbox"
            title={
              <div className="flex items-center gap-2">
                <ChatBubbleLeftRightIcon className="w-4 h-4" />
                <span>شاشة المحادثات المباشرة (WhatsApp Web)</span>
              </div>
            }
          />
          <Tab
            key="logs"
            title={
              <div className="flex items-center gap-2">
                <QueueListIcon className="w-4 h-4" />
                <span>سجل وتفاصيل الرسائل ({counts.total})</span>
              </div>
            }
          />
        </Tabs>
      </div>

      {/* View 1: Live Chat Inbox Cockpit */}
      {pageMode === 'inbox' && (
        <WhatsAppLiveInbox onOpenTemplates={() => setIsTemplatesModalOpen(true)} />
      )}

      {/* View 2: Logs Table */}
      {pageMode === 'logs' && (
        <Card className="border border-default-200 shadow-sm">
        <CardBody className="p-4 sm:p-6 space-y-4">
          {/* Status Tabs with Counters */}
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="overflow-x-auto w-full md:w-auto pb-1">
              <Tabs
                selectedKey={activeTab}
                onSelectionChange={(key) => {
                  setActiveTab(key as string);
                  setCurrentPage(1);
                }}
                color="primary"
                variant="solid"
                disableAnimation
                classNames={{
                  tabList: 'bg-default-100 p-1.5 rounded-xl whitespace-nowrap gap-1 border border-default-200/50',
                  tab: 'data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground font-semibold text-xs px-3.5 py-2 rounded-lg transition-colors',
                }}
              >
                <Tab
                  key="all"
                  title={
                    <div className="flex items-center gap-1.5">
                      <span>{ar.whatsapp.tabs.all}</span>
                      <span className={`text-xs font-mono ${activeTab === 'all' ? 'text-primary-foreground/90 font-bold' : 'text-default-400'}`}>
                        ({counts.total})
                      </span>
                    </div>
                  }
                />
                <Tab
                  key="queued"
                  title={
                    <div className="flex items-center gap-1.5">
                      <span>{ar.whatsapp.tabs.queued}</span>
                      <span className={`text-xs font-mono ${activeTab === 'queued' ? 'text-primary-foreground/90 font-bold' : 'text-default-400'}`}>
                        ({counts.queued})
                      </span>
                    </div>
                  }
                />
                <Tab
                  key="sent"
                  title={
                    <div className="flex items-center gap-1.5">
                      <span>{ar.whatsapp.tabs.sent}</span>
                      <span className={`text-xs font-mono ${activeTab === 'sent' ? 'text-primary-foreground/90 font-bold' : 'text-default-400'}`}>
                        ({counts.sent})
                      </span>
                    </div>
                  }
                />
                <Tab
                  key="delivered"
                  title={
                    <div className="flex items-center gap-1.5">
                      <span>{ar.whatsapp.tabs.delivered}</span>
                      <span className={`text-xs font-mono ${activeTab === 'delivered' ? 'text-primary-foreground/90 font-bold' : 'text-default-400'}`}>
                        ({counts.delivered})
                      </span>
                    </div>
                  }
                />
                <Tab
                  key="read"
                  title={
                    <div className="flex items-center gap-1.5">
                      <span>{ar.whatsapp.tabs.read}</span>
                      <span className={`text-xs font-mono ${activeTab === 'read' ? 'text-primary-foreground/90 font-bold' : 'text-default-400'}`}>
                        ({counts.read})
                      </span>
                    </div>
                  }
                />
                <Tab
                  key="failed"
                  title={
                    <div className={`flex items-center gap-1.5 font-semibold ${activeTab === 'failed' ? 'text-primary-foreground' : 'text-danger-600'}`}>
                      <span>{ar.whatsapp.tabs.failed}</span>
                      <span className={`text-xs font-mono ${activeTab === 'failed' ? 'text-primary-foreground/90 font-bold' : 'text-danger-500'}`}>
                        ({counts.failed})
                      </span>
                    </div>
                  }
                />
              </Tabs>
            </div>

            {/* Search Input */}
            <div className="w-full md:w-72">
              <SearchInput
                placeholder={ar.whatsapp.searchPlaceholder}
                value={searchQuery}
                onSearch={(val) => {
                  setSearchQuery(val);
                  setDebouncedSearch(val);
                  setCurrentPage(1);
                }}
                size="sm"
                variant="bordered"
                className="w-full"
              />
            </div>
          </div>

          {/* Messages Table */}
          <DataTable
            columns={columns}
            data={messages}
            keyExtractor={(msg) => msg.id}
            isLoading={isLoading}
            currentPage={currentPage}
            lastPage={totalPages}
            total={counts.total}
            onPageChange={setCurrentPage}
            emptyTitle={ar.common.noData}
            emptyIcon={<ChatBubbleLeftRightIcon className="w-8 h-8 opacity-40" />}
          />
        </CardBody>
      </Card>
      )}

      {/* Templates Management Modal */}
      <WhatsAppTemplatesModal
        isOpen={isTemplatesModalOpen}
        onClose={() => setIsTemplatesModalOpen(false)}
        onSaved={loadMessages}
      />

      {/* Driver Chat Modal */}
      {selectedDriver && (
        <DriverChatModal
          isOpen={Boolean(selectedDriver)}
          driverId={selectedDriver.id}
          driverName={selectedDriver.name}
          driverPhone={selectedDriver.phone}
          onClose={() => setSelectedDriver(null)}
          onStatusChanged={loadMessages}
        />
      )}
    </PageContainer>
  );
};

export default WhatsAppMessagesPage;
