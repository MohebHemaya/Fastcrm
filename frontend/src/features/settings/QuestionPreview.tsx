import React from 'react';
import type { QuestionType, QuestionOption } from '@/types/question';
import { DevicePhoneMobileIcon, CheckIcon, QueueListIcon } from '@/components/icons';

interface QuestionPreviewProps {
  textAr: string;
  type: QuestionType;
  options: QuestionOption[];
}

export const QuestionPreview: React.FC<QuestionPreviewProps> = ({
  textAr,
  type,
  options,
}) => {
  const displayOptions = options.filter((o) => o.label_ar.trim() !== '');
  const isButtons = displayOptions.length <= 3;

  return (
    <div className="flex flex-col items-center">
      <div className="flex items-center gap-1.5 text-xs text-default-500 mb-2 font-medium">
        <DevicePhoneMobileIcon className="w-4 h-4 text-emerald-500" />
        <span>معاينة حية كما تظهر للسائق على واتساب</span>
      </div>

      {/* WhatsApp Simulated Phone Frame */}
      <div className="w-full max-w-[320px] rounded-3xl border-4 border-slate-700 bg-[#0b141a] p-3 shadow-xl text-white font-sans overflow-hidden">
        {/* Chat Header */}
        <div className="flex items-center gap-2 border-b border-white/10 pb-2 mb-3 px-1">
          <div className="w-7 h-7 rounded-full bg-emerald-600 flex items-center justify-center font-bold text-xs">
            ن
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-xs font-semibold truncate text-white">النقل السريع</p>
            <p className="text-[10px] text-emerald-400">متصل الآن</p>
          </div>
        </div>

        {/* Date Divider */}
        <div className="flex justify-center mb-2.5">
          <span className="bg-[#182229] text-[10px] text-slate-400 px-2.5 py-0.5 rounded-md shadow-sm">
            اليوم
          </span>
        </div>

        {/* Inbound Message Bubble */}
        <div className="bg-[#202c33] rounded-2xl rounded-tr-none p-3 shadow-md max-w-[92%] ms-auto text-start relative mb-2">
          {/* Question Text */}
          <p className="text-xs text-white leading-relaxed whitespace-pre-wrap">
            {textAr.trim() || 'اكتب نص السؤال هنا لمعاينته مباشرة...'}
          </p>

          <div className="flex items-center justify-end gap-1 mt-1.5 text-[9px] text-slate-400">
            <span>12:00 م</span>
            <div className="inline-flex -space-x-1">
              <CheckIcon className="w-3 h-3 text-sky-400" />
              <CheckIcon className="w-3 h-3 text-sky-400" />
            </div>
          </div>

          {/* Interactive Question Elements */}
          {type === 'yes_no' && (
            <div className="mt-2.5 pt-2 border-t border-white/10 flex flex-col gap-1.5">
              <button
                type="button"
                className="w-full py-1.5 px-3 bg-[#2a3942] hover:bg-[#32424b] text-sky-400 text-xs font-medium rounded-lg text-center transition-colors"
              >
                نعم
              </button>
              <button
                type="button"
                className="w-full py-1.5 px-3 bg-[#2a3942] hover:bg-[#32424b] text-sky-400 text-xs font-medium rounded-lg text-center transition-colors"
              >
                لا
              </button>
            </div>
          )}

          {type === 'single_choice' && (
            <div className="mt-2.5 pt-2 border-t border-white/10">
              {displayOptions.length === 0 ? (
                <p className="text-[10px] text-amber-300 italic text-center">
                  أضف خيارات لمعاينتها كأزرار أو قائمة
                </p>
              ) : isButtons ? (
                <div className="flex flex-col gap-1.5">
                  {displayOptions.map((opt, idx) => (
                    <button
                      key={idx}
                      type="button"
                      className="w-full py-1.5 px-3 bg-[#2a3942] hover:bg-[#32424b] text-sky-400 text-xs font-medium rounded-lg text-center truncate transition-colors"
                    >
                      {opt.label_ar}
                    </button>
                  ))}
                  <p className="text-[9px] text-slate-400 text-center mt-1">
                    (أزرار رد سريع - حتى 3 خيارات)
                  </p>
                </div>
              ) : (
                <div className="flex flex-col gap-1.5">
                  <button
                    type="button"
                    className="w-full py-2 px-3 bg-[#00a884] hover:bg-[#008f6f] text-[#111b21] font-semibold text-xs rounded-lg flex items-center justify-center gap-1.5 shadow transition-colors"
                  >
                    <QueueListIcon className="w-3.5 h-3.5" />
                    <span>اختر إجابة ({displayOptions.length} خيارات)</span>
                  </button>
                  <div className="bg-[#182229] rounded-lg p-1.5 border border-white/5 space-y-1">
                    {displayOptions.slice(0, 4).map((opt, idx) => (
                      <div
                        key={idx}
                        className="text-[10px] text-slate-300 px-2 py-1 bg-white/5 rounded truncate"
                      >
                        • {opt.label_ar}
                      </div>
                    ))}
                    {displayOptions.length > 4 && (
                      <p className="text-[9px] text-slate-400 text-center">
                        + {displayOptions.length - 4} خيارات أخرى في القائمة
                      </p>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

          {type === 'number' && (
            <div className="mt-2.5 pt-2 border-t border-white/10 text-center">
              <span className="text-[10px] bg-white/10 text-slate-300 px-2 py-0.5 rounded-full inline-block">
                يرد السائق بإرسال رقم (مثال: 5)
              </span>
            </div>
          )}

          {type === 'text' && (
            <div className="mt-2.5 pt-2 border-t border-white/10 text-center">
              <span className="text-[10px] bg-white/10 text-slate-300 px-2 py-0.5 rounded-full inline-block">
                يرد السائق بنص حر عادي
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default QuestionPreview;
