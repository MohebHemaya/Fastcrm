import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Textarea,
  Tabs,
  Tab,
  Chip,
  Card,
  CardBody,
  Spinner,
} from '@heroui/react';
import {
  DocumentTextIcon,
  SparklesIcon,
  ArrowPathIcon,
  CheckIcon,
  ChatBubbleLeftRightIcon,
  PlusIcon,
  TrashIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import { whatsappService } from '@/services/whatsappService';
import type { WhatsAppTemplateDefinition, QuickReplyItem } from '@/types/whatsapp';

interface WhatsAppTemplatesModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSaved?: () => void;
}

export const WhatsAppTemplatesModal: React.FC<WhatsAppTemplatesModalProps> = ({
  isOpen,
  onClose,
  onSaved,
}) => {
  const addToast = useUiStore((state) => state.addToast);
  const [activeTab, setActiveTab] = useState<'templates' | 'quick_replies'>('templates');
  const [templates, setTemplates] = useState<WhatsAppTemplateDefinition[]>([]);
  const [editedTemplates, setEditedTemplates] = useState<Record<string, string>>({});
  const [editedReplies, setEditedReplies] = useState<QuickReplyItem[]>([]);
  const [selectedTemplateKey, setSelectedTemplateKey] = useState<string>('order_confirmation');
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSaving, setIsSaving] = useState<boolean>(false);

  const loadData = async () => {
    setIsLoading(true);
    try {
      const res = await whatsappService.getTemplates();
      const tpls = res.data.templates || [];
      const replies = res.data.quick_replies || [];
      setTemplates(tpls);

      const map: Record<string, string> = {};
      tpls.forEach((t) => {
        map[t.key] = t.current_text;
      });
      setEditedTemplates(map);
      setEditedReplies(replies);

      if (tpls.length > 0 && !selectedTemplateKey) {
        setSelectedTemplateKey(tpls[0].key);
      }
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: 'فشل تحميل قوالب الرسائل',
        type: 'error',
      });
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      loadData();
    }
  }, [isOpen]);

  const handleInsertVariable = (tag: string) => {
    const current = editedTemplates[selectedTemplateKey] || '';
    setEditedTemplates({
      ...editedTemplates,
      [selectedTemplateKey]: current ? `${current} ${tag}` : tag,
    });
  };

  const handleResetToDefault = (key: string) => {
    const tpl = templates.find((t) => t.key === key);
    if (tpl) {
      setEditedTemplates({
        ...editedTemplates,
        [key]: tpl.default_text,
      });
      addToast({
        title: 'تم الاسترجاع',
        message: 'تم استرجاع النص الافتراضي للقالب',
        type: 'info',
      });
    }
  };

  const handleAddQuickReply = () => {
    const newReply: QuickReplyItem = {
      id: `reply_${Date.now()}`,
      title: 'رد جديد',
      text: '',
    };
    setEditedReplies([...editedReplies, newReply]);
  };

  const handleRemoveQuickReply = (index: number) => {
    setEditedReplies(editedReplies.filter((_, i) => i !== index));
  };

  const handleSave = async () => {
    setIsSaving(true);
    try {
      await whatsappService.updateTemplates({
        templates: editedTemplates,
        quick_replies: editedReplies,
      });

      addToast({
        title: 'تم الحفظ بنجاح',
        message: 'تم تحديث صيغ وقوالب رسائل الواتساب والردود الجاهزة',
        type: 'success',
      });
      onSaved?.();
      onClose();
    } catch (e: any) {
      addToast({
        title: ar.errors.generalError,
        message: e?.response?.data?.message || 'فشل حفظ القوالب',
        type: 'error',
      });
    } finally {
      setIsSaving(false);
    }
  };

  const activeTemplate = templates.find((t) => t.key === selectedTemplateKey);

  // Generate live preview text with dummy variables
  const renderLivePreview = (text: string) => {
    if (!text) return '';
    return text
      .replace(/{{اسم_السائق}}|{{driver_name}}/g, 'أحمد محمود')
      .replace(/{{رقم_الطلب}}|{{order_code}}/g, 'WO-0104')
      .replace(/{{مكان_الانطلاق}}|{{from_location}}/g, 'مستودعات العاشر من رمضان')
      .replace(/{{جهة_الوصول}}|{{وجهة_الوصول}}|{{to_location}}/g, 'ميناء دمياط البحري')
      .replace(/{{موعد_الرحلة}}|{{scheduled_at}}/g, '2026-10-10 08:00 ص')
      .replace(/{{المقابل_المادي}}|{{pay_amount}}/g, '2,500 ج.م')
      .replace(/{{نوع_الحمولة}}|{{cargo_type}}/g, 'حاويات جافة')
      .replace(/{{الملاحظات}}|{{notes}}/g, '- يرجى إحضار تصريح التحميل')
      .replace(/{{رقم_الترتيب}}/g, '1')
      .replace(/{{سبب_الإلغاء}}|{{cancel_reason}}/g, 'تأجيل موعد الشحن من العميل')
      .replace(/{{عدد_الرحلات}}/g, '5');
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="5xl"
      scrollBehavior="inside"
      classNames={{
        base: 'max-h-[92vh]',
      }}
    >
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1 border-b border-default-200 pb-3">
          <div className="flex items-center gap-2">
            <div className="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
              <DocumentTextIcon className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-lg font-bold text-default-900">
                إدارة صيغ وقوالب رسائل الواتساب التلقائية
              </h3>
              <p className="text-xs text-default-500 font-normal">
                تخصيص الكلمات والمتغيرات التي تصل للسائقين في كل مرحلة من مراحل التشغيل
              </p>
            </div>
          </div>
        </ModalHeader>

        <ModalBody className="p-4 sm:p-6">
          {isLoading ? (
            <div className="py-20 flex flex-col items-center justify-center gap-3">
              <Spinner size="lg" color="primary" />
              <p className="text-xs text-default-500">جاري تحميل قوالب الرسائل...</p>
            </div>
          ) : (
            <div className="space-y-6">
              {/* Tabs Switcher */}
              <Tabs
                selectedKey={activeTab}
                onSelectionChange={(k) => setActiveTab(k as any)}
                color="primary"
                variant="bordered"
                classNames={{
                  tabList: 'w-full sm:w-auto',
                }}
              >
                <Tab
                  key="templates"
                  title={
                    <div className="flex items-center gap-2 text-xs font-bold">
                      <SparklesIcon className="w-4 h-4 text-emerald-600" />
                      <span>قوالب الرسائل الآلية ({templates.length})</span>
                    </div>
                  }
                />
                <Tab
                  key="quick_replies"
                  title={
                    <div className="flex items-center gap-2 text-xs font-bold">
                      <ChatBubbleLeftRightIcon className="w-4 h-4 text-primary" />
                      <span>الردود السريعة الجاهزة في الشات ({editedReplies.length})</span>
                    </div>
                  }
                />
              </Tabs>

              {activeTab === 'templates' && (
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
                  {/* Sidebar list of templates */}
                  <div className="lg:col-span-4 space-y-2">
                    <p className="text-xs font-bold text-default-600 mb-2">اختر مرحلة أو حالة الرسالة:</p>
                    <div className="space-y-1.5 max-h-[500px] overflow-y-auto pe-1">
                      {templates.map((tpl) => {
                        const isSelected = selectedTemplateKey === tpl.key;
                        return (
                          <button
                            key={tpl.key}
                            type="button"
                            onClick={() => setSelectedTemplateKey(tpl.key)}
                            className={`w-full text-start p-3 rounded-xl border transition-all flex flex-col gap-1 ${
                              isSelected
                                ? 'bg-primary-50/70 border-primary-500 shadow-xs ring-1 ring-primary-500'
                                : 'bg-content1 hover:bg-default-100 border-default-200'
                            }`}
                          >
                            <span className={`text-xs font-bold ${isSelected ? 'text-primary-700' : 'text-default-800'}`}>
                              {tpl.title_ar}
                            </span>
                            <span className="text-[11px] text-default-500 line-clamp-1">
                              {tpl.description_ar}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  {/* Template Editor & Preview */}
                  <div className="lg:col-span-8 space-y-4">
                    {activeTemplate && (
                      <>
                        <div className="p-3.5 bg-default-50 border border-default-200 rounded-xl space-y-1">
                          <div className="flex items-center justify-between">
                            <h4 className="text-sm font-bold text-default-900">{activeTemplate.title_ar}</h4>
                            <Button
                              size="sm"
                              variant="light"
                              color="warning"
                              className="text-xs h-7"
                              startContent={<ArrowPathIcon className="w-3.5 h-3.5" />}
                              onPress={() => handleResetToDefault(activeTemplate.key)}
                            >
                              استعادة الافتراضي
                            </Button>
                          </div>
                          <p className="text-xs text-default-500">{activeTemplate.description_ar}</p>
                        </div>

                        {/* Variables helper */}
                        <div className="space-y-1.5">
                          <label className="text-xs font-bold text-default-700 flex items-center gap-1.5">
                            <span>المتغيرات المتاحة للإدراج:</span>
                            <span className="text-[11px] text-default-400 font-normal">
                              (اضغط لإضافة المتغير إلى نص الرسالة)
                            </span>
                          </label>
                          <div className="flex flex-wrap gap-1.5">
                            {activeTemplate.variables.map((v) => (
                              <Chip
                                key={v.tag}
                                size="sm"
                                variant="flat"
                                color="primary"
                                className="cursor-pointer hover:bg-primary-100 transition-colors text-xs font-mono select-none"
                                onClick={() => handleInsertVariable(v.tag)}
                              >
                                {v.tag} <span className="font-sans text-[10px] text-default-500">({v.label_ar})</span>
                              </Chip>
                            ))}
                          </div>
                        </div>

                        {/* Editor Textarea */}
                        <div className="space-y-1">
                          <label className="text-xs font-bold text-default-700">صيغة الرسالة:</label>
                          <Textarea
                            value={editedTemplates[activeTemplate.key] || ''}
                            onValueChange={(val) =>
                              setEditedTemplates({
                                ...editedTemplates,
                                [activeTemplate.key]: val,
                              })
                            }
                            minRows={4}
                            maxRows={8}
                            variant="bordered"
                            placeholder="اكتب صيغة الرسالة هنا..."
                            className="w-full font-sans text-sm"
                          />
                        </div>

                        {/* Live WhatsApp Bubble Preview */}
                        <div className="space-y-1.5">
                          <label className="text-xs font-bold text-default-700">معاينة مباشرة في الواتساب (شاشة السائق):</label>
                          <div className="p-4 rounded-2xl bg-[#efeae2] dark:bg-[#0b141a] border border-default-200">
                            <div className="max-w-[85%] bg-[#d9fdd3] dark:bg-[#005c4b] text-[#111b21] dark:text-[#e9edef] rounded-2xl rounded-tr-none p-3 shadow-xs text-xs whitespace-pre-wrap leading-relaxed">
                              {renderLivePreview(editedTemplates[activeTemplate.key] || '')}
                              <div className="text-[10px] text-default-400 text-end mt-1 font-mono">
                                {new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })} ✓✓
                              </div>
                            </div>
                          </div>
                        </div>
                      </>
                    )}
                  </div>
                </div>
              )}

              {activeTab === 'quick_replies' && (
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-bold text-default-900">الردود السريعة الجاهزة (Canned Responses)</h4>
                      <p className="text-xs text-default-500">
                        تظهر كأزرار سريعة بنقرة واحدة فوق خانة كتابة الرسالة في شاشة الشات المباشر
                      </p>
                    </div>
                    <Button
                      size="sm"
                      color="primary"
                      variant="flat"
                      startContent={<PlusIcon className="w-4 h-4" />}
                      onPress={handleAddQuickReply}
                      className="font-bold text-xs"
                    >
                      إضافة رد جديد
                    </Button>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {editedReplies.map((reply, idx) => (
                      <Card key={reply.id} className="border border-default-200 shadow-xs bg-content1">
                        <CardBody className="p-3.5 space-y-2.5">
                          <div className="flex items-center justify-between gap-2">
                            <input
                              type="text"
                              value={reply.title}
                              onChange={(e) => {
                                const copy = [...editedReplies];
                                copy[idx].title = e.target.value;
                                setEditedReplies(copy);
                              }}
                              className="text-xs font-bold bg-transparent border-b border-default-300 focus:border-primary outline-hidden py-0.5 px-1 w-full"
                              placeholder="عنوان الزر (مثلاً: 📍 اللوكيشن)"
                            />
                            <Button
                              isIconOnly
                              size="sm"
                              variant="light"
                              color="danger"
                              onPress={() => handleRemoveQuickReply(idx)}
                              aria-label="حذف"
                            >
                              <TrashIcon className="w-4 h-4" />
                            </Button>
                          </div>
                          <Textarea
                            value={reply.text}
                            onValueChange={(val) => {
                              const copy = [...editedReplies];
                              copy[idx].text = val;
                              setEditedReplies(copy);
                            }}
                            minRows={2}
                            maxRows={4}
                            size="sm"
                            variant="bordered"
                            placeholder="نص الرد الذي سيتم إدراجه عند النقر..."
                            className="text-xs"
                          />
                        </CardBody>
                      </Card>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </ModalBody>

        <ModalFooter className="border-t border-default-200">
          <Button variant="flat" onPress={onClose} isDisabled={isSaving}>
            إلغاء
          </Button>
          <Button
            color="primary"
            onPress={handleSave}
            isLoading={isSaving}
            startContent={!isSaving && <CheckIcon className="w-4 h-4" />}
            className="font-bold"
          >
            حفظ الصيغ والقوالب
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default WhatsAppTemplatesModal;
