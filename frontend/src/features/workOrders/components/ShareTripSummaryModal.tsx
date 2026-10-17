import React, { useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Chip,
} from '@heroui/react';
import {
  ChatBubbleLeftRightIcon,
  CheckIcon,
  DocumentDuplicateIcon,
} from '@/components/icons';
import type { WorkOrder, WorkOrderRecipient } from '@/types/workOrder';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';

interface ShareTripSummaryModalProps {
  isOpen: boolean;
  onClose: () => void;
  order: WorkOrder;
  confirmedRecipients: WorkOrderRecipient[];
}

export const ShareTripSummaryModal: React.FC<ShareTripSummaryModalProps> = ({
  isOpen,
  onClose,
  order,
  confirmedRecipients,
}) => {
  const addToast = useUiStore((state) => state.addToast);
  const [copied, setCopied] = useState(false);

  const formattedDate = order.scheduled_at
    ? new Date(order.scheduled_at).toLocaleString('ar-EG', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    : 'غير محدد';

  const driversListText =
    confirmedRecipients.length > 0
      ? confirmedRecipients
          .map(
            (r, i) =>
              `${i + 1}. ${r.driver_name || 'كابتن'} (${r.driver_phone || 'بدون رقم'})`
          )
          .join('\n')
      : 'لا يوجد سائقين مؤكدين بعد.';

  const summaryText = `*بيان رحلة عمل - Fast CRM*
━━━━━━━━━━━━━━━━━━
- *كود الرحلة:* ${order.code}
- *خط السير:* من ${order.from_location || 'غير محدد'} إلى ${order.to_location || 'غير محدد'}
- *الموعد:* ${formattedDate}
- *المقابل المادي:* ${order.pay_amount ? `${order.pay_amount} ج.م` : 'حسب الاتفاق'}
- *نوع الحمولة:* ${order.cargo_type || 'عامة'}
- *المطلوب:* ${order.trips_needed} سيارة (المؤكد حالياً: ${confirmedRecipients.length})
${order.notes ? `- *ملاحظات:* ${order.notes}\n` : ''}━━━━━━━━━━━━━━━━━━
*السائقون المؤكدون للرحلة:*
${driversListText}
━━━━━━━━━━━━━━━━━━`;

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(summaryText);
      setCopied(true);
      addToast({
        message: ar.workOrders.shareWhatsAppCopied,
        type: 'success',
      });
      setTimeout(() => setCopied(false), 2000);
    } catch {
      addToast({
        message: 'فشل النسخ تلقائياً، يرجى التحديد والنسخ يدوياً',
        type: 'error',
      });
    }
  };

  const handleOpenWhatsApp = () => {
    const encoded = encodeURIComponent(summaryText);
    window.open(`https://wa.me/?text=${encoded}`, '_blank');
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg" placement="center">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2.5 pb-2">
          <ChatBubbleLeftRightIcon className="w-5 h-5 text-primary shrink-0" />
          <div className="flex flex-col text-start">
            <span className="text-base font-bold text-foreground">
              {ar.workOrders.shareWhatsApp}
            </span>
            <span className="text-xs text-default-400 font-normal">
              مشاركة ملخص بيانات الرحلة والسائقين المؤكدين مع العميل أو جروب المشرفين
            </span>
          </div>
        </ModalHeader>

        <ModalBody className="space-y-3 pt-2">
          {/* WhatsApp Preview Bubble */}
          <div className="rounded-xl border border-default-200 bg-[#efeae2] dark:bg-[#0b141a] p-3 sm:p-4">
            <div className="bg-white dark:bg-[#1f2c34] text-default-900 dark:text-default-100 rounded-xl rounded-tr-none p-3.5 border border-default-200 dark:border-default-700 text-start space-y-2">
              <pre className="text-xs leading-relaxed font-sans whitespace-pre-wrap select-all">
                {summaryText}
              </pre>
              <div className="flex items-center justify-between pt-1 border-t border-default-100 dark:border-default-800 text-[10px] text-default-400">
                <Chip size="sm" variant="flat" color="success" className="h-5 text-[10px] font-bold">
                  {confirmedRecipients.length} سائق مؤكد
                </Chip>
              </div>
            </div>
          </div>
        </ModalBody>

        <ModalFooter className="flex flex-col sm:flex-row gap-2">
          <Button
            variant="flat"
            onPress={handleCopy}
            startContent={
              copied ? <CheckIcon className="w-4 h-4 text-success" /> : <DocumentDuplicateIcon className="w-4 h-4" />
            }
            className="w-full sm:w-auto font-semibold"
          >
            {copied ? 'تم النسخ بنجاح' : 'نسخ النص للحافظة'}
          </Button>

          <Button
            color="success"
            className="w-full sm:w-auto text-white font-bold bg-[#25D366] hover:bg-[#20ba5a]"
            startContent={<ChatBubbleLeftRightIcon className="w-4 h-4" />}
            onPress={handleOpenWhatsApp}
          >
            مشاركة في واتساب
          </Button>

          <Button variant="light" onPress={onClose} className="w-full sm:w-auto">
            إغلاق
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default ShareTripSummaryModal;
