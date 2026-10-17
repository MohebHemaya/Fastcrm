import React, { useState, useRef } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
} from '@heroui/react';
import {
  PhotoIcon,
  PaperAirplaneIcon,
  XMarkIcon,
  ExclamationCircleIcon,
} from '@/components/icons';
import { whatsappService } from '@/services/whatsappService';
import { useUiStore } from '@/lib/stores/useUiStore';
import type { WhatsAppMessage } from '@/types/whatsapp';

interface ChatMediaAttachmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  driverId: number;
  driverName?: string;
  onSuccess: (msg: WhatsAppMessage) => void;
}

export const ChatMediaAttachmentModal: React.FC<ChatMediaAttachmentModalProps> = ({
  isOpen,
  onClose,
  driverId,
  driverName,
  onSuccess,
}) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [caption, setCaption] = useState<string>('');
  const [isSending, setIsSending] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const addToast = useUiStore((state) => state.addToast);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setErrorMsg(null);

    // Max 10MB check
    if (file.size > 10 * 1024 * 1024) {
      setErrorMsg('الحد الأقصى لحجم الصورة هو 10 ميجابايت');
      return;
    }

    const isImg = file.type.startsWith('image/');
    if (!isImg) {
      setErrorMsg('الصيغة غير مدعومة. يرجى اختيار صورة (JPG, PNG, WEBP, GIF)');
      return;
    }

    setSelectedFile(file);

    const objectUrl = URL.createObjectURL(file);
    setPreviewUrl(objectUrl);
  };

  const handleReset = () => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
    setSelectedFile(null);
    setPreviewUrl(null);
    setCaption('');
    setErrorMsg(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleClose = () => {
    handleReset();
    onClose();
  };

  const handleSend = async () => {
    if (!selectedFile) {
      setErrorMsg('يرجى اختيار صورة للإرسال');
      return;
    }

    setIsSending(true);
    setErrorMsg(null);

    try {
      const formData = new FormData();
      formData.append('type', 'image');
      formData.append('file', selectedFile);
      if (caption.trim()) {
        formData.append('caption', caption.trim());
      }

      const res = await whatsappService.sendMedia(driverId, formData);
      addToast({
        title: 'تم الإرسال',
        message: 'تم إرسال الصورة للسائق بنجاح',
        type: 'success',
      });
      onSuccess(res.data);
      handleClose();
    } catch (err: any) {
      const msg = err?.response?.data?.message || 'فشل إرسال الصورة المرفقة';
      setErrorMsg(msg);
      addToast({
        title: 'خطأ في الإرسال',
        message: msg,
        type: 'error',
      });
    } finally {
      setIsSending(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="lg" backdrop="blur">
      <ModalContent>
        <ModalHeader className="border-b border-default-200 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <PhotoIcon className="w-5 h-5 text-primary-500" />
            <span className="text-sm font-bold">إرسال صورة عبر واتساب إلى {driverName || 'السائق'}</span>
          </div>
          <Button isIconOnly size="sm" variant="light" onPress={handleClose} aria-label="إغلاق">
            <XMarkIcon className="w-4 h-4" />
          </Button>
        </ModalHeader>

        <ModalBody className="p-4 space-y-4">
          <input
            ref={fileInputRef}
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            className="hidden"
            onChange={handleFileChange}
          />

          {!selectedFile ? (
            <div
              onClick={() => fileInputRef.current?.click()}
              className="border-2 border-dashed border-default-300 hover:border-primary-500 rounded-2xl p-8 flex flex-col items-center justify-center gap-3 cursor-pointer transition-colors bg-default-50 hover:bg-primary-50/20 text-center"
            >
              <PhotoIcon className="w-10 h-10 text-primary-500" />
              <div>
                <p className="text-xs font-bold text-default-800">اضغط هنا لاختيار صورة</p>
                <p className="text-[11px] text-default-500 mt-1">
                  يدعم صور JPG, PNG, WEBP, GIF حتى 10 ميجابايت
                </p>
              </div>
            </div>
          ) : (
            <div className="space-y-3">
              {/* Preview area */}
              <div className="relative rounded-xl overflow-hidden border border-default-200 bg-black/5 dark:bg-black/30 p-2 flex flex-col items-center justify-center">
                <Button
                  isIconOnly
                  size="sm"
                  color="danger"
                  variant="flat"
                  className="absolute top-2 start-2 z-10 w-7 h-7 min-w-7"
                  onPress={handleReset}
                  title="إلغاء الصورة"
                >
                  <XMarkIcon className="w-3.5 h-3.5" />
                </Button>

                {previewUrl && (
                  <img
                    src={previewUrl}
                    alt="معاينة"
                    className="max-h-[260px] w-auto rounded-lg object-contain"
                  />
                )}
              </div>

              {/* Caption input */}
              <Input
                size="sm"
                variant="bordered"
                label="نص توضيحي للصورة (اختياري)"
                placeholder="اكتب ملاحظة أو وصفاً للصورة..."
                value={caption}
                onChange={(e) => setCaption(e.target.value)}
                className="text-xs"
              />
            </div>
          )}

          {errorMsg && (
            <div className="p-2.5 rounded-lg bg-danger-50 text-danger-700 text-xs flex items-center gap-2">
              <ExclamationCircleIcon className="w-4 h-4 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}
        </ModalBody>

        <ModalFooter className="border-t border-default-200 py-2.5">
          <Button variant="light" size="sm" onPress={handleClose} disabled={isSending}>
            إلغاء
          </Button>
          <Button
            color="primary"
            size="sm"
            onPress={handleSend}
            isLoading={isSending}
            disabled={!selectedFile || isSending}
            startContent={!isSending && <PaperAirplaneIcon className="w-4 h-4 rtl:rotate-180" />}
            className="font-bold min-w-[100px]"
          >
            إرسال الصورة
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default ChatMediaAttachmentModal;
