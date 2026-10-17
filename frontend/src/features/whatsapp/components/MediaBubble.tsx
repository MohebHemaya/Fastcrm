import React, { useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Button,
} from '@heroui/react';
import {
  PaperClipIcon,
  ArrowDownTrayIcon,
  MagnifyingGlassPlusIcon,
  XMarkIcon,
} from '@/components/icons';
import type { WhatsAppMessage } from '@/types/whatsapp';

interface MediaBubbleProps {
  msg: WhatsAppMessage;
}

export const MediaBubble: React.FC<MediaBubbleProps> = ({ msg }) => {
  const payload = msg.payload ?? {};
  const mediaUrl: string | undefined = payload.media_url ?? payload.url;
  const rawType: string = (payload.media_type ?? payload.type ?? msg.type ?? 'unknown').toLowerCase();
  const fileName: string = payload.file_name ?? payload.filename ?? 'مرفق';
  const caption: string | undefined = payload.caption ?? (msg.body && msg.body !== 'صورة 📷' ? msg.body : undefined);

  const [isLightboxOpen, setIsLightboxOpen] = useState(false);

  if (!mediaUrl) return null;

  const isImage =
    rawType === 'image' ||
    rawType.startsWith('image') ||
    rawType === 'photo' ||
    mediaUrl.match(/\.(jpeg|jpg|png|webp|gif)($|\?)/i);

  // ─── IMAGE VIEWER & LIGHTBOX ─────────────────────────────────────────────
  if (isImage) {
    return (
      <div className="space-y-1.5 max-w-[260px] sm:max-w-[280px]">
        <div
          className="relative group rounded-xl overflow-hidden border border-default-200/80 bg-default-100 cursor-pointer shadow-sm"
          onClick={() => setIsLightboxOpen(true)}
        >
          <img
            src={mediaUrl}
            alt={caption || fileName}
            className="w-full max-h-[220px] object-cover transition-transform duration-200 group-hover:scale-105"
            loading="lazy"
          />
          <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5 text-white text-xs font-bold">
            <MagnifyingGlassPlusIcon className="w-4 h-4" />
            <span>عرض الصورة</span>
          </div>
        </div>

        {caption && (
          <p className="text-xs text-default-800 dark:text-default-200 leading-snug px-0.5">
            {caption}
          </p>
        )}

        {/* Lightbox Modal */}
        <Modal
          isOpen={isLightboxOpen}
          onClose={() => setIsLightboxOpen(false)}
          size="3xl"
          scrollBehavior="inside"
          backdrop="blur"
        >
          <ModalContent>
            <ModalHeader className="flex items-center justify-between border-b border-default-200 py-2.5">
              <span className="text-sm font-bold truncate">{caption || fileName || 'معاينة الصورة'}</span>
              <div className="flex items-center gap-2">
                <Button
                  as="a"
                  href={mediaUrl}
                  download="whatsapp_image.jpg"
                  target="_blank"
                  rel="noopener noreferrer"
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<ArrowDownTrayIcon className="w-4 h-4" />}
                >
                  تحميل
                </Button>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  onPress={() => setIsLightboxOpen(false)}
                  aria-label="إغلاق"
                >
                  <XMarkIcon className="w-4 h-4" />
                </Button>
              </div>
            </ModalHeader>
            <ModalBody className="p-4 flex items-center justify-center bg-black/90 min-h-[300px]">
              <img
                src={mediaUrl}
                alt={caption || fileName}
                className="max-h-[75vh] w-auto max-w-full rounded-lg object-contain shadow-2xl"
              />
            </ModalBody>
          </ModalContent>
        </Modal>
      </div>
    );
  }

  // ─── DOCUMENT / ATTACHMENT ────────────────────────────────────────────────
  return (
    <a
      href={mediaUrl}
      target="_blank"
      rel="noopener noreferrer"
      className="flex items-center gap-2.5 bg-default-100 dark:bg-default-800 rounded-xl px-3 py-2 text-primary-600 dark:text-primary-400 hover:bg-default-200 transition-colors border border-default-200/60 max-w-[260px] text-xs font-semibold"
    >
      <PaperClipIcon className="w-4 h-4 shrink-0 text-default-500" />
      <span className="truncate flex-1">{fileName}</span>
      <ArrowDownTrayIcon className="w-3.5 h-3.5 shrink-0 text-default-400" />
    </a>
  );
};

export default MediaBubble;
