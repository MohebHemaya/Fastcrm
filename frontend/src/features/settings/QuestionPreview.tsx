import React, { useState } from 'react';
import type { QuestionType, QuestionOption } from '@/types/question';
import { DevicePhoneMobileIcon, CheckIcon, ChartBarIcon } from '@/components/icons';

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
  const [selectedPollOption, setSelectedPollOption] = useState<number | null>(null);

  const displayOptions =
    type === 'yes_no'
      ? [
          { value: 'yes', label_ar: 'نعم' },
          { value: 'no', label_ar: 'لا' },
        ]
      : options.filter((o) => o.label_ar && o.label_ar.trim() !== '');

  return (
    <div className="flex flex-col items-center">
      <div className="flex items-center gap-1.5 text-xs text-default-500 mb-2 font-medium">
        <DevicePhoneMobileIcon className="w-4 h-4 text-emerald-500" />
        <span>معاينة حية كما تظهر للسائق على واتساب</span>
      </div>

      {/* WhatsApp Simulated Phone Frame */}
      <div className="w-full max-w-[340px] rounded-[36px] border-[6px] border-slate-800 bg-[#0b141a] p-3.5 shadow-2xl text-white font-sans overflow-hidden transition-all duration-300">
        {/* Phone Notch & Speaker bar */}
        <div className="flex justify-center mb-2">
          <div className="w-20 h-3.5 bg-slate-900 rounded-full flex items-center justify-center gap-1.5 px-2">
            <span className="w-1.5 h-1.5 rounded-full bg-slate-700" />
            <span className="w-8 h-1 rounded-full bg-slate-700" />
          </div>
        </div>

        {/* WhatsApp Chat Header */}
        <div className="flex items-center justify-between border-b border-white/10 pb-2.5 mb-3 px-1">
          <div className="flex items-center gap-2">
            {/* Fast Express Burgundy Avatar */}
            <div className="w-8 h-8 rounded-full bg-[#7D0E40] border border-white/20 flex items-center justify-center font-bold text-xs text-white shadow-sm">
              ف
            </div>
            <div className="flex-1 min-w-0 text-start">
              <p className="text-xs font-bold truncate text-white leading-tight">فاست اكسبريس</p>
              <div className="flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                <p className="text-[10px] text-emerald-400 leading-none">متصل الآن</p>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2 text-slate-400 text-xs">
            <span className="text-[11px] bg-emerald-950/80 text-emerald-400 border border-emerald-800/60 px-1.5 py-0.5 rounded text-[10px] font-mono">
              Wassenger
            </span>
          </div>
        </div>

        {/* Date Divider */}
        <div className="flex justify-center mb-3">
          <span className="bg-[#182229] text-[10px] text-slate-400 px-3 py-0.5 rounded-full shadow-inner">
            اليوم
          </span>
        </div>

        {/* Inbound Message Bubble */}
        <div className="bg-[#202c33] rounded-2xl rounded-tr-none p-3.5 shadow-lg max-w-[96%] ms-auto text-start relative mb-2 border border-white/5">
          {/* Question Text / Poll Title */}
          <h4 className="text-xs font-bold text-white leading-relaxed whitespace-pre-wrap mb-1">
            {textAr.trim() || 'اكتب نص السؤال هنا لمعاينته مباشرة...'}
          </h4>

          {/* Interactive Wassenger Native Poll Layout for single_choice & yes_no */}
          {(type === 'single_choice' || type === 'yes_no') && (
            <div className="mt-2.5 pt-2 border-t border-white/10 space-y-2">
              {/* Poll Subtitle */}
              <div className="flex items-center justify-between text-[10px] text-slate-400 pb-1">
                <span className="flex items-center gap-1 text-slate-300 font-medium">
                  <ChartBarIcon className="w-3 h-3 text-[#00a884]" />
                  <span>استطلاع رأي • اختر إجابة واحدة</span>
                </span>
                <span className="text-[9px] text-[#00a884] font-semibold bg-[#00a884]/10 px-1.5 py-0.5 rounded">
                  إجابة واحدة
                </span>
              </div>

              {displayOptions.length === 0 ? (
                <div className="p-3 rounded-lg bg-amber-950/30 border border-amber-800/40 text-[11px] text-amber-200 text-center">
                  أضف خيارات لمعاينتها كاستطلاع رأي تفاعلي على واتساب
                </div>
              ) : (
                <div className="space-y-1.5">
                  {displayOptions.map((opt, idx) => {
                    const isSelected = selectedPollOption === idx;
                    return (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => setSelectedPollOption(isSelected ? null : idx)}
                        className={`w-full group relative overflow-hidden text-start p-2 rounded-xl border transition-all flex items-center justify-between gap-2.5 ${
                          isSelected
                            ? 'bg-[#00a884]/20 border-[#00a884] text-white shadow-sm'
                            : 'bg-[#2a3942]/60 hover:bg-[#2a3942] border-white/5 text-slate-200'
                        }`}
                      >
                        {/* Vote background bar if selected */}
                        {isSelected && (
                          <div
                            className="absolute inset-y-0 start-0 bg-[#00a884]/25 transition-all duration-300 pointer-events-none"
                            style={{ width: '100%' }}
                          />
                        )}

                        {/* Label & Option indicator */}
                        <div className="flex items-center gap-2.5 relative z-10 min-w-0 flex-1">
                          {/* Radio button circle */}
                          <div
                            className={`w-4 h-4 rounded-full flex items-center justify-center shrink-0 transition-colors ${
                              isSelected
                                ? 'border-2 border-[#00a884] bg-[#00a884]'
                                : 'border-2 border-slate-400 group-hover:border-slate-300'
                            }`}
                          >
                            {isSelected && <span className="w-1.5 h-1.5 rounded-full bg-[#111b21]" />}
                          </div>
                          <span className="text-xs font-medium truncate">{opt.label_ar}</span>
                        </div>

                        {/* Vote count */}
                        <span className="relative z-10 text-[10px] font-mono text-slate-400 shrink-0">
                          {isSelected ? '1 صوت (100%)' : '0'}
                        </span>
                      </button>
                    );
                  })}

                  {/* View Votes Footer link */}
                  <div className="pt-2 flex items-center justify-between text-[10px] text-slate-400 border-t border-white/5">
                    <span className="text-[#00a884] hover:underline cursor-pointer font-medium">
                      عرض الأصوات (View votes)
                    </span>
                    <span className="text-[9px] text-slate-500">
                      {selectedPollOption !== null ? 'إجمالي: 1 صوت' : 'لم يتم التصويت بعد'}
                    </span>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Number Type Preview */}
          {type === 'number' && (
            <div className="mt-2.5 pt-2 border-t border-white/10 space-y-2">
              <div className="p-2 rounded-lg bg-white/5 border border-white/5 text-center">
                <span className="text-[11px] text-sky-300 font-medium block">
                  🔢 نوع السؤال: رقم
                </span>
                <span className="text-[10px] text-slate-400">
                  يرد السائق بإرسال رقم فقط (مثال: 5، 3، 10)
                </span>
              </div>
            </div>
          )}

          {/* Text Type Preview */}
          {type === 'text' && (
            <div className="mt-2.5 pt-2 border-t border-white/10 space-y-2">
              <div className="p-2 rounded-lg bg-white/5 border border-white/5 text-center">
                <span className="text-[11px] text-emerald-300 font-medium block">
                  💬 نوع السؤال: نص حر
                </span>
                <span className="text-[10px] text-slate-400">
                  يرد السائق بكتابة رسالة نصية عادية
                </span>
              </div>
            </div>
          )}

          {/* Time & Read Receipts */}
          <div className="flex items-center justify-end gap-1 mt-2 text-[9px] text-slate-400">
            <span>12:00 م</span>
            <div className="inline-flex -space-x-1">
              <CheckIcon className="w-3 h-3 text-sky-400" />
              <CheckIcon className="w-3 h-3 text-sky-400" />
            </div>
          </div>
        </div>

        {/* WhatsApp Bottom Chat Input Bar Simulator */}
        <div className="mt-3 pt-2 border-t border-white/10 flex items-center gap-1.5 px-1">
          <div className="flex-1 bg-[#202c33] rounded-full px-3 py-1.5 text-[10px] text-slate-400 flex items-center justify-between">
            <span>رسالة...</span>
            <span className="text-slate-500">📎</span>
          </div>
          <div className="w-6 h-6 rounded-full bg-[#00a884] flex items-center justify-center text-[10px] text-[#111b21] font-bold">
            🎙️
          </div>
        </div>
      </div>

      {/* Helper caption */}
      <p className="text-[11px] text-default-400 mt-2 text-center max-w-xs">
        💡 استطلاعات الرأي تتيح للسائق الاختيار بنقرة واحدة داخل محادثة واتساب بدون كتابة أرقام يدوياً.
      </p>
    </div>
  );
};

export default QuestionPreview;
