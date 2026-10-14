import React, { useState, useEffect, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm, Controller, useFieldArray, useWatch } from 'react-hook-form';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Checkbox,
  RadioGroup,
  Radio,
  Select,
  SelectItem,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  ArrowRightIcon,
  ArrowLeftIcon,
  CheckIcon,
  PlusIcon,
  TrashIcon,
  UsersIcon,
  PaperAirplaneIcon,
  DocumentArrowDownIcon,
  MapPinIcon,
  ExclamationCircleIcon,
  ChatBubbleLeftRightIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
} from '@/components/icons';
import { FormInput, FormSelect, FormTextarea } from '@/components/form';
import {
  EGYPT_GOVERNORATES,
  getCitiesForGovernorate,
  formatLocationString,
  parseLocationString,
} from '@/data/egyptLocations';
import PageHeader from '@/components/ui/PageHeader';
import PageContainer from '@/components/layout/PageContainer';
import { ar } from '@/i18n/ar';
import { workOrderService } from '@/services/workOrderService';
import { questionService } from '@/services/questionService';
import { driverService } from '@/services/driverService';
import type { WhatsappGroup } from '@/types/driver';
import type { Question } from '@/types/question';
import type {
  CreateWorkOrderPayload,
  WorkOrderFilterOperator,
  MatchPreviewResponse,
} from '@/types/workOrder';
import { useUiStore } from '@/lib/stores/useUiStore';

export const DEFAULT_OFFER_MESSAGE = `فرصة رحلة جديدة 🚗
مرحبًا يا {{driver_name}}، تتوفر رحلة جديدة مطابقة لبياناتك:
- رقم الطلب: {{order_code}}
- خط السير: من {{from_location}} إلى {{to_location}}
- الموعد: {{scheduled_at}}
- المقابل المادي: {{pay_amount}}
- نوع الحمولة: {{cargo_type}}
{{notes}}

هل ترغب في قبول هذه الرحلة؟ (الأولوية لأسبقية الرد)`;

const MESSAGE_VARIABLES = [
  { tag: '{{driver_name}}', label: 'اسم السائق' },
  { tag: '{{order_code}}', label: 'رقم الطلب' },
  { tag: '{{from_location}}', label: 'مكان الانطلاق' },
  { tag: '{{to_location}}', label: 'وجهة الوصول' },
  { tag: '{{scheduled_at}}', label: 'موعد الرحلة' },
  { tag: '{{pay_amount}}', label: 'المقابل المادي' },
  { tag: '{{cargo_type}}', label: 'نوع الحمولة' },
  { tag: '{{notes}}', label: 'الملاحظات' },
  { tag: '{{trips_needed}}', label: 'عدد الرحلات' },
];

interface FormValues {
  // Step 1: Details
  from_governorate: string;
  from_city: string;
  from_details: string;
  from_location: string;

  to_governorate: string;
  to_city: string;
  to_details: string;
  to_location: string;

  scheduled_at: string;
  scheduled_dispatch_at?: string;
  offer_expires_in_minutes?: string | number;
  cargo_type?: string;
  pay_amount: string;
  notes: string;

  // Step 2: Capacity
  trips_needed: number;
  backup_needed: number;
  exclude_same_day_confirmed: boolean;

  // Step 3: Filters
  filters: Array<{
    question_id: number;
    operator: WorkOrderFilterOperator;
    value: any;
  }>;
  min_avg_rating?: string;
  max_no_shows?: string;
  last_trip_within_days?: string;
  include_unrated?: boolean;
  target_group_id?: string;
  driver_experience?: 'all' | 'new_only' | 'experienced_only';
  driver_phone_filter?: string;

  // Step 4: Strategy & Custom Message
  send_strategy: 'all' | 'staged';
  batch_size: number;
  batch_interval_minutes: number;
  custom_message?: string;
}

const STORAGE_KEY = 'fast_crm_work_order_draft';

const DEFAULT_FORM_VALUES: FormValues = {
  from_governorate: '',
  from_city: '',
  from_details: '',
  from_location: '',
  to_governorate: '',
  to_city: '',
  to_details: '',
  to_location: '',
  scheduled_at: '',
  scheduled_dispatch_at: '',
  offer_expires_in_minutes: '',
  cargo_type: '',
  pay_amount: '',
  notes: '',
  trips_needed: 1,
  backup_needed: 0,
  exclude_same_day_confirmed: true,
  filters: [],
  min_avg_rating: '',
  max_no_shows: '',
  last_trip_within_days: '',
  include_unrated: false,
  target_group_id: '',
  driver_experience: 'all',
  driver_phone_filter: '',
  send_strategy: 'all',
  batch_size: 10,
  batch_interval_minutes: 5,
  custom_message: DEFAULT_OFFER_MESSAGE,
};

const STEPS = [
  { id: 1, title: ar.workOrders.steps.details },
  { id: 2, title: ar.workOrders.steps.capacity },
  { id: 3, title: ar.workOrders.steps.filters },
  { id: 4, title: ar.workOrders.steps.strategy },
  { id: 5, title: ar.workOrders.steps.review },
];

export const CreateWorkOrderPage: React.FC = () => {
  const navigate = useNavigate();
  const addToast = useUiStore((state) => state.addToast);

  const [currentStep, setCurrentStep] = useState(1);
  const [filterQuestions, setFilterQuestions] = useState<Question[]>([]);
  const [whatsappGroups, setWhatsappGroups] = useState<WhatsappGroup[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitMode, setSubmitMode] = useState<'draft' | 'send'>('draft');

  // Auto-save & crash recovery state
  const [isDraftRestored, setIsDraftRestored] = useState(false);
  const [draftSavedTime, setDraftSavedTime] = useState<string | null>(null);
  const hasRestoredRef = useRef(false);

  // Live match state
  const [matchPreview, setMatchPreview] = useState<MatchPreviewResponse | null>(null);
  const [isMatchingLoading, setIsMatchingLoading] = useState(false);

  const {
    control,
    handleSubmit,
    watch,
    trigger,
    setValue,
    getValues,
    reset,
  } = useForm<FormValues>({
    defaultValues: DEFAULT_FORM_VALUES,
    mode: 'onBlur',
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'filters',
  });

  // Watch location dropdowns and synced values
  const watchedFromGov = watch('from_governorate');
  const watchedFromCity = watch('from_city');
  const watchedFromLocation = watch('from_location');

  const watchedToGov = watch('to_governorate');
  const watchedToCity = watch('to_city');
  const watchedToLocation = watch('to_location');

  const fromCityOptions = useMemo(
    () => getCitiesForGovernorate(watchedFromGov),
    [watchedFromGov]
  );
  const toCityOptions = useMemo(
    () => getCitiesForGovernorate(watchedToGov),
    [watchedToGov]
  );

  // Synchronize formatted location strings smoothly
  const syncFromLocation = () => {
    const gov = getValues('from_governorate');
    const city = getValues('from_city');
    const details = getValues('from_details');
    if (gov && city) {
      const formatted = formatLocationString(gov, city, details);
      if (formatted !== getValues('from_location')) {
        setValue('from_location', formatted);
      }
    } else if (details) {
      if (details !== getValues('from_location')) {
        setValue('from_location', details);
      }
    } else if (getValues('from_location')) {
      setValue('from_location', '');
    }
  };

  const syncToLocation = () => {
    const gov = getValues('to_governorate');
    const city = getValues('to_city');
    const details = getValues('to_details');
    if (gov && city) {
      const formatted = formatLocationString(gov, city, details);
      if (formatted !== getValues('to_location')) {
        setValue('to_location', formatted);
      }
    } else if (details) {
      if (details !== getValues('to_location')) {
        setValue('to_location', details);
      }
    } else if (getValues('to_location')) {
      setValue('to_location', '');
    }
  };

  // Clear city and update formatted string when governorate changes
  const prevFromGovRef = useRef(watchedFromGov);
  useEffect(() => {
    if (prevFromGovRef.current !== watchedFromGov) {
      if (prevFromGovRef.current) {
        setValue('from_city', '');
      }
      prevFromGovRef.current = watchedFromGov;
      syncFromLocation();
    }
  }, [watchedFromGov, setValue]);

  useEffect(() => {
    syncFromLocation();
  }, [watchedFromCity]);

  const prevToGovRef = useRef(watchedToGov);
  useEffect(() => {
    if (prevToGovRef.current !== watchedToGov) {
      if (prevToGovRef.current) {
        setValue('to_city', '');
      }
      prevToGovRef.current = watchedToGov;
      syncToLocation();
    }
  }, [watchedToGov, setValue]);

  useEffect(() => {
    syncToLocation();
  }, [watchedToCity]);

  // If from_location or to_location is populated externally (duplication, tests, or deep links)
  useEffect(() => {
    if (watchedFromLocation && !watchedFromGov) {
      const parsed = parseLocationString(watchedFromLocation);
      if (parsed.governorate) {
        setValue('from_governorate', parsed.governorate);
      }
      if (parsed.city) {
        setValue('from_city', parsed.city);
      }
      if (parsed.details) {
        setValue('from_details', parsed.details);
      }
    }
  }, [watchedFromLocation, watchedFromGov, setValue]);

  useEffect(() => {
    if (watchedToLocation && !watchedToGov) {
      const parsed = parseLocationString(watchedToLocation);
      if (parsed.governorate) {
        setValue('to_governorate', parsed.governorate);
      }
      if (parsed.city) {
        setValue('to_city', parsed.city);
      }
      if (parsed.details) {
        setValue('to_details', parsed.details);
      }
    }
  }, [watchedToLocation, watchedToGov, setValue]);

  // Watch fields needed for live match preview with useWatch for deep reactivity
  const watchedFilters = useWatch({ control, name: 'filters' });
  const watchedExcludeSameDay = useWatch({ control, name: 'exclude_same_day_confirmed' });
  const watchedScheduledAt = useWatch({ control, name: 'scheduled_at' });
  const watchedMinAvgRating = useWatch({ control, name: 'min_avg_rating' });
  const watchedMaxNoShows = useWatch({ control, name: 'max_no_shows' });
  const watchedLastTripWithinDays = useWatch({ control, name: 'last_trip_within_days' });
  const watchedIncludeUnrated = useWatch({ control, name: 'include_unrated' });
  const watchedTargetGroupId = useWatch({ control, name: 'target_group_id' });
  const watchedDriverExperience = useWatch({ control, name: 'driver_experience' });
  const watchedPhoneFilter = useWatch({ control, name: 'driver_phone_filter' });

  const watchedStrategy = watch('send_strategy');
  const watchedCustomMessage = watch('custom_message');
  const watchedPayAmount = watch('pay_amount');
  const watchedCargoType = watch('cargo_type');
  const watchedNotes = watch('notes');

  const serializedFilters = useMemo(() => JSON.stringify(watchedFilters ?? []), [watchedFilters]);

  const messageTextareaRef = useRef<HTMLTextAreaElement | null>(null);

  const insertVariable = (tag: string) => {
    const currentMsg = watch('custom_message') ?? DEFAULT_OFFER_MESSAGE;
    const textarea = messageTextareaRef.current;

    if (!textarea) {
      setValue('custom_message', currentMsg + tag, { shouldDirty: true });
      return;
    }

    const start = textarea.selectionStart ?? currentMsg.length;
    const end = textarea.selectionEnd ?? currentMsg.length;
    const updated = currentMsg.slice(0, start) + tag + currentMsg.slice(end);

    setValue('custom_message', updated, { shouldDirty: true });

    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(start + tag.length, start + tag.length);
    }, 10);
  };

  const handleResetMessage = () => {
    setValue('custom_message', DEFAULT_OFFER_MESSAGE, { shouldDirty: true });
  };

  const renderPreviewMessage = (templateText?: string) => {
    const text = templateText || DEFAULT_OFFER_MESSAGE;
    const driverName = 'أحمد محمد (كابتن)';
    const code = 'WO-20261006-0001';
    const fromLoc = watchedFromLocation || 'مخازن العاشر من رمضان';
    const toLoc = watchedToLocation || 'ميناء الإسكندرية';
    const scheduled = watchedScheduledAt
      ? new Date(watchedScheduledAt).toLocaleString('ar-EG', {
          weekday: 'short',
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
      : 'الثلاثاء ٦ أكتوبر، ٠٣:٠٠ م';
    const pay = watchedPayAmount ? `${watchedPayAmount} ج.م` : 'حسب الاتفاق';
    const cargo = watchedCargoType || 'عامة';
    const notesStr = watchedNotes ? `- ملاحظات: ${watchedNotes}` : '';
    const trips = String(watch('trips_needed') || 1);

    return text
      .replace(/{{driver_name}}|{{اسم_السائق}}/g, driverName)
      .replace(/{{order_code}}|{{code}}|{{رقم_الطلب}}/g, code)
      .replace(/{{from_location}}|{{مكان_الانطلاق}}/g, fromLoc)
      .replace(/{{to_location}}|{{وجهة_الوصول}}/g, toLoc)
      .replace(/{{scheduled_at}}|{{موعد_الرحلة}}/g, scheduled)
      .replace(/{{pay_amount}}|{{المقابل_المادي}}/g, pay)
      .replace(/{{cargo_type}}|{{نوع_الحمولة}}/g, cargo)
      .replace(/{{notes}}|{{الملاحظات}}/g, notesStr)
      .replace(/{{trips_needed}}|{{عدد_الرحلات}}/g, trips);
  };

  const renderWhatsAppPreview = (messageText?: string) => {
    const renderedBody = renderPreviewMessage(messageText);

    return (
      <div className="w-full max-w-md rounded-2xl border border-default-200 bg-[#e5ddd5] dark:bg-[#0b141a] overflow-hidden shadow-sm">
        {/* WhatsApp App Bar */}
        <div className="bg-[#075e54] text-white px-3.5 py-2.5 flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-full bg-emerald-700/80 border border-white/30 flex items-center justify-center font-bold text-xs text-white">
              📱
            </div>
            <div className="flex flex-col text-start">
              <span className="text-xs font-bold leading-tight">إدارة الرحلات (نظام التشغيل)</span>
              <span className="text-[10px] text-emerald-200">حساب أعمال رسمي ✓</span>
            </div>
          </div>
          <Chip size="sm" variant="flat" className="bg-white/20 text-white text-[10px] h-5 font-bold">
            معاينة حية
          </Chip>
        </div>

        {/* WhatsApp Chat Content */}
        <div className="p-3 sm:p-4 space-y-2.5 bg-[#efeae2]/80 dark:bg-[#0b141a]">
          {/* WhatsApp Message Bubble */}
          <div className="bg-white dark:bg-[#1f2c34] text-default-900 dark:text-default-100 rounded-2xl rounded-tr-none p-3.5 shadow-sm border border-black/5 dark:border-white/5 text-start space-y-1.5">
            <p className="text-xs sm:text-[13px] leading-relaxed whitespace-pre-wrap font-sans text-foreground">
              {renderedBody}
            </p>
            <div className="flex items-center justify-end gap-1 pt-1 text-[10px] text-default-400">
              <span>١٠:٣٠ ص</span>
              <span className="text-primary font-bold">✓✓</span>
            </div>
          </div>

          {/* Interactive WhatsApp Buttons */}
          <div className="space-y-1.5 pt-1">
            <div className="w-full py-2 px-3 bg-white dark:bg-[#1f2c34] text-primary dark:text-primary-400 text-xs font-bold text-center rounded-xl shadow-xs border border-default-200 dark:border-default-700/50 flex items-center justify-center gap-1.5 cursor-default select-none">
              <CheckIcon className="w-3.5 h-3.5 text-success" />
              <span>{ar.workOrders.messageTemplate.btnAccept}</span>
            </div>
            <div className="w-full py-2 px-3 bg-white dark:bg-[#1f2c34] text-danger dark:text-danger-400 text-xs font-semibold text-center rounded-xl shadow-xs border border-default-200 dark:border-default-700/50 flex items-center justify-center gap-1.5 cursor-default select-none">
              <span>{ar.workOrders.messageTemplate.btnDecline}</span>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Load questions and WhatsApp groups for step 3
  useEffect(() => {
    const loadQuestions = async () => {
      try {
        const res = await questionService.getQuestions();
        const available = res.filter((q) => q.is_filter);
        setFilterQuestions(available);
      } catch (err) {
        console.error('Failed to load filter questions', err);
      }
    };

    const loadGroups = async () => {
      try {
        const res = await driverService.getWhatsappGroups();
        setWhatsappGroups(res.data);
      } catch (err) {
        console.error('Failed to load WhatsApp groups', err);
      }
    };

    loadQuestions();
    loadGroups();
  }, []);

  // Debounced live match preview
  useEffect(() => {
    // Only query when on or after step 3
    if (currentStep < 3) return;

    const timer = setTimeout(async () => {
      setIsMatchingLoading(true);
      try {
        // Sanitize filters: only include those with valid question_id and value
        const validFilters = (watchedFilters || [])
          .filter((f) => {
            if (!f || !f.question_id || !f.operator) return false;
            if (Array.isArray(f.value)) return f.value.length > 0;
            return f.value !== '' && f.value !== undefined;
          })
          .map((f) => ({
            question_id: Number(f.question_id),
            operator: f.operator,
            value: f.value,
          }));

        const res = await workOrderService.previewMatch({
          filters: validFilters,
          exclude_same_day_confirmed: watchedExcludeSameDay,
          scheduled_at: watchedScheduledAt || undefined,
          min_avg_rating: watchedMinAvgRating ? Number(watchedMinAvgRating) : undefined,
          max_no_shows:
            watchedMaxNoShows !== '' && watchedMaxNoShows !== undefined
              ? Number(watchedMaxNoShows)
              : undefined,
          last_trip_within_days: watchedLastTripWithinDays
            ? Number(watchedLastTripWithinDays)
            : undefined,
          include_unrated: watchedIncludeUnrated,
          target_group_id: watchedTargetGroupId ? Number(watchedTargetGroupId) : undefined,
          driver_experience: watchedDriverExperience || 'all',
          phone: watchedPhoneFilter || undefined,
        });
        setMatchPreview(res.data);
      } catch (err) {
        console.error('Failed to fetch match preview', err);
      } finally {
        setIsMatchingLoading(false);
      }
    }, 400);

    return () => clearTimeout(timer);
  }, [
    serializedFilters,
    watchedExcludeSameDay,
    watchedScheduledAt,
    watchedMinAvgRating,
    watchedMaxNoShows,
    watchedLastTripWithinDays,
    watchedIncludeUnrated,
    watchedTargetGroupId,
    watchedDriverExperience,
    watchedPhoneFilter,
    currentStep,
  ]);

  // Restore draft on mount
  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        const hasContent = Boolean(
          parsed.from_governorate ||
          parsed.to_governorate ||
          parsed.scheduled_at ||
          parsed.cargo_type ||
          parsed.pay_amount ||
          parsed.notes ||
          (Array.isArray(parsed.filters) && parsed.filters.length > 0) ||
          (parsed.custom_message && parsed.custom_message !== DEFAULT_OFFER_MESSAGE)
        );

        if (hasContent) {
          reset({
            ...DEFAULT_FORM_VALUES,
            ...parsed,
          });
          if (
            parsed._currentStep &&
            typeof parsed._currentStep === 'number' &&
            parsed._currentStep >= 1 &&
            parsed._currentStep <= STEPS.length
          ) {
            setCurrentStep(parsed._currentStep);
          }
          if (parsed._savedAt) {
            setDraftSavedTime(
              new Date(parsed._savedAt).toLocaleTimeString('ar-EG', {
                hour: '2-digit',
                minute: '2-digit',
              })
            );
          }
          setIsDraftRestored(true);
          addToast({
            message: 'تم استرجاع المسودة التي كنت تعمل عليها تلقائياً 💾',
            type: 'info',
          });
        }
      }
    } catch (e) {
      console.error('Failed to restore draft from localStorage', e);
    } finally {
      hasRestoredRef.current = true;
    }
  }, [reset, addToast]);

  // Auto-save draft on changes (subscription without re-rendering)
  useEffect(() => {
    const subscription = watch((currentVals) => {
      if (!hasRestoredRef.current) return;
      const hasContent = Boolean(
        currentVals.from_governorate ||
        currentVals.to_governorate ||
        currentVals.scheduled_at ||
        currentVals.cargo_type ||
        currentVals.pay_amount ||
        currentVals.notes ||
        (Array.isArray(currentVals.filters) && currentVals.filters.length > 0) ||
        (currentVals.custom_message && currentVals.custom_message !== DEFAULT_OFFER_MESSAGE)
      );

      if (hasContent) {
        const now = new Date();
        localStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({
            ...currentVals,
            _currentStep: currentStep,
            _savedAt: now.toISOString(),
          })
        );
        setDraftSavedTime(
          now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
        );
      }
    });

    return () => subscription.unsubscribe();
  }, [watch, currentStep]);

  const handleDiscardDraft = () => {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      // Ignore
    }
    reset(DEFAULT_FORM_VALUES);
    setCurrentStep(1);
    setIsDraftRestored(false);
    setDraftSavedTime(null);
    addToast({
      message: 'تم مسح المسودة والبدء بأمر شغل جديد فارغ',
      type: 'info',
    });
  };

  // Validation per step
  const validateStep = async (step: number): Promise<boolean> => {
    switch (step) {
      case 1: {
        syncFromLocation();
        syncToLocation();
        return true;
      }
      case 2:
        return await trigger(['trips_needed', 'backup_needed']);
      case 3:
        return true;
      case 4:
        if (watchedStrategy === 'staged') {
          return await trigger(['batch_size', 'batch_interval_minutes']);
        }
        return true;
      default:
        return true;
    }
  };

  const handleNext = async () => {
    const isValid = await validateStep(currentStep);
    if (isValid) {
      setCurrentStep((prev) => Math.min(prev + 1, STEPS.length));
    }
  };

  const handlePrev = () => {
    setCurrentStep((prev) => Math.max(prev - 1, 1));
  };

  // Submit Handler
  const onSubmit = async (data: FormValues) => {
    if (isSubmitting) return;
    setIsSubmitting(true);
    try {
      const validFilters = (data.filters || [])
        .filter((f) => {
          if (!f || !f.question_id || !f.operator) return false;
          if (Array.isArray(f.value)) return f.value.length > 0;
          return f.value !== '' && f.value !== undefined;
        })
        .map((f) => ({
          question_id: Number(f.question_id),
          operator: f.operator,
          value: f.operator === 'gte' || f.operator === 'lte' ? Number(f.value) : f.value,
        }));

      syncFromLocation();
      syncToLocation();
      const currentFrom = getValues('from_location');
      const currentTo = getValues('to_location');

      const payload: CreateWorkOrderPayload = {
        from_location: currentFrom?.trim() || undefined,
        to_location: currentTo?.trim() || undefined,
        scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toISOString() : undefined,
        scheduled_dispatch_at: data.scheduled_dispatch_at ? new Date(data.scheduled_dispatch_at).toISOString() : undefined,
        offer_expires_in_minutes: data.offer_expires_in_minutes ? Number(data.offer_expires_in_minutes) : undefined,
        cargo_type: data.cargo_type?.trim() || undefined,
        pay_amount: data.pay_amount ? Number(data.pay_amount) : undefined,
        notes: data.notes || undefined,
        trips_needed: Number(data.trips_needed),
        backup_needed: Number(data.backup_needed || 0),
        exclude_same_day_confirmed: data.exclude_same_day_confirmed,
        min_avg_rating: data.min_avg_rating ? Number(data.min_avg_rating) : undefined,
        max_no_shows:
          data.max_no_shows !== '' && data.max_no_shows !== undefined
            ? Number(data.max_no_shows)
            : undefined,
        last_trip_within_days: data.last_trip_within_days
          ? Number(data.last_trip_within_days)
          : undefined,
        include_unrated: data.include_unrated,
        target_group_id: data.target_group_id ? Number(data.target_group_id) : undefined,
        driver_experience: data.driver_experience || 'all',
        custom_message: data.custom_message?.trim() || undefined,
        send_strategy: data.send_strategy,
        batch_size: data.send_strategy === 'staged' ? Number(data.batch_size) : undefined,
        batch_interval_minutes:
          data.send_strategy === 'staged' ? Number(data.batch_interval_minutes) : undefined,
        filters: validFilters,
      };

      const res = await workOrderService.createDraft(payload);
      const createdOrder = res.data;

      // Clear saved draft on successful creation
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch (e) {
        // Ignore
      }

      if (submitMode === 'send') {
        await workOrderService.sendOrder(createdOrder.id);
        addToast({ message: ar.workOrders.sendSuccess, type: 'success' });
      } else {
        addToast({ message: ar.workOrders.draftSaved, type: 'success' });
      }

      navigate(`/work-orders/${createdOrder.id}`);
    } catch (err: any) {
      console.error('Failed to create work order', err);
      const message =
        err?.response?.data?.message || err?.message || ar.errors.generalError;
      addToast({ message, type: 'error' });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <PageContainer maxWidth="max-w-4xl" className="space-y-6">
      {/* Header */}
      <PageHeader
        title={ar.workOrders.createOrder}
        description={ar.workOrders.subtitle}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: ar.workOrders.title, href: '/work-orders' },
          { label: ar.workOrders.createOrder },
        ]}
      />

      {/* Draft Restored Banner */}
      {isDraftRestored && (
        <Card className="border border-primary-200 dark:border-primary-800 bg-primary-50/80 dark:bg-primary-950/40 shadow-xs">
          <CardBody className="p-3 sm:p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div className="flex items-center gap-2.5 text-xs text-primary-950 dark:text-primary-100">
              <span className="text-base leading-none">💾</span>
              <div>
                <span className="font-bold">تم استرجاع المسودة التي كنت تعمل عليها تلقائياً.</span>
                {draftSavedTime && (
                  <span className="text-primary-700 dark:text-primary-300 ms-1.5 font-medium">
                    (آخر حفظ تلقائي: {draftSavedTime})
                  </span>
                )}
                <span className="block sm:inline sm:ms-1 text-default-500">
                  يمكنك استكمال ملء البيانات أو البدء من جديد.
                </span>
              </div>
            </div>
            <div className="flex items-center gap-2 self-end sm:self-auto shrink-0">
              <Button
                size="sm"
                variant="flat"
                color="danger"
                className="font-medium text-xs h-7 px-3"
                onPress={handleDiscardDraft}
              >
                مسح المسودة والبدء من جديد
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Stepper Navigation */}
      <Card className="shadow-sm">
        <CardBody className="p-3 sm:p-4">
          {/* Mobile Stepper (< md) */}
          <div className="flex md:hidden flex-col gap-2">
            <div className="flex items-center justify-between text-xs">
              <span className="font-bold text-primary">
                الخطوة {currentStep} من {STEPS.length}: {STEPS[currentStep - 1]?.title}
              </span>
              <span className="text-default-400 font-medium">
                {Math.round((currentStep / STEPS.length) * 100)}%
              </span>
            </div>
            <div className="h-1.5 w-full bg-default-100 rounded-full overflow-hidden">
              <div
                className="h-full bg-primary transition-all duration-300 rounded-full"
                style={{ width: `${(currentStep / STEPS.length) * 100}%` }}
              />
            </div>
          </div>

          {/* Desktop/Tablet Stepper (>= md) */}
          <div className="hidden md:flex items-center justify-between overflow-x-auto gap-2">
            {STEPS.map((step) => {
              const isCurrent = step.id === currentStep;
              const isCompleted = step.id < currentStep;
              return (
                <div
                  key={step.id}
                  className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors whitespace-nowrap ${
                    isCurrent
                      ? 'bg-primary-50 text-primary font-semibold'
                      : isCompleted
                      ? 'text-success font-medium'
                      : 'text-default-400'
                  }`}
                >
                  <span
                    className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                      isCurrent
                        ? 'bg-primary text-white'
                        : isCompleted
                        ? 'bg-success text-white'
                        : 'bg-default-200 text-default-600'
                    }`}
                  >
                    {isCompleted ? <CheckIcon className="w-3.5 h-3.5" /> : step.id}
                  </span>
                  <span>{step.title}</span>
                </div>
              );
            })}
          </div>
        </CardBody>
      </Card>

      {/* Main Multi-Step Form */}
      <form onSubmit={handleSubmit(onSubmit)}>
        {/* STEP 1: Details */}
        {currentStep === 1 && (
          <Card className="shadow-sm">
            <CardHeader className="font-semibold text-lg pb-0">
              {ar.workOrders.steps.details}
            </CardHeader>
            <CardBody className="space-y-4 pt-4">
              {/* Location Selectors: Departure and Destination */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {/* مكان الانطلاق */}
                <div className="p-4 border border-default-200 dark:border-default-100 rounded-xl bg-default-50/50 dark:bg-default-50/10 space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2 font-semibold text-foreground text-sm">
                      <div className="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <MapPinIcon className="w-4 h-4" />
                      </div>
                      <span>{ar.workOrders.fields.fromLocation}</span>
                      <span className="text-default-400 text-xs font-normal ms-1">(اختياري)</span>
                    </div>
                    {watchedFromLocation && (
                      <span
                        className="text-xs px-2.5 py-1 rounded-full bg-primary/10 text-primary font-medium truncate max-w-[220px]"
                        title={watchedFromLocation}
                      >
                        {watchedFromLocation}
                      </span>
                    )}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormSelect
                      name="from_governorate"
                      control={control}
                      label={ar.workOrders.fields.fromGovernorate}
                      placeholder={ar.workOrders.fields.fromGovernoratePlaceholder}
                      options={EGYPT_GOVERNORATES}
                    />

                    <FormSelect
                      name="from_city"
                      control={control}
                      label={ar.workOrders.fields.fromCity}
                      placeholder={
                        watchedFromGov
                          ? ar.workOrders.fields.fromCityPlaceholder
                          : ar.workOrders.fields.selectGovernorateFirst
                      }
                      options={fromCityOptions}
                      isDisabled={!watchedFromGov}
                    />
                  </div>

                  <FormInput
                    name="from_details"
                    control={control}
                    label={ar.workOrders.fields.fromDetails}
                    placeholder={ar.workOrders.fields.fromDetailsPlaceholder}
                    onBlur={syncFromLocation}
                  />
                </div>

                {/* وجهة الوصول */}
                <div className="p-4 border border-default-200 dark:border-default-100 rounded-xl bg-default-50/50 dark:bg-default-50/10 space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2 font-semibold text-foreground text-sm">
                      <div className="w-7 h-7 rounded-lg bg-success/10 text-success flex items-center justify-center">
                        <MapPinIcon className="w-4 h-4" />
                      </div>
                      <span>{ar.workOrders.fields.toLocation}</span>
                      <span className="text-default-400 text-xs font-normal ms-1">(اختياري)</span>
                    </div>
                    {watchedToLocation && (
                      <span
                        className="text-xs px-2.5 py-1 rounded-full bg-success/10 text-success font-medium truncate max-w-[220px]"
                        title={watchedToLocation}
                      >
                        {watchedToLocation}
                      </span>
                    )}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormSelect
                      name="to_governorate"
                      control={control}
                      label={ar.workOrders.fields.toGovernorate}
                      placeholder={ar.workOrders.fields.toGovernoratePlaceholder}
                      options={EGYPT_GOVERNORATES}
                    />

                    <FormSelect
                      name="to_city"
                      control={control}
                      label={ar.workOrders.fields.toCity}
                      placeholder={
                        watchedToGov
                          ? ar.workOrders.fields.toCityPlaceholder
                          : ar.workOrders.fields.selectGovernorateFirst
                      }
                      options={toCityOptions}
                      isDisabled={!watchedToGov}
                    />
                  </div>

                  <FormInput
                    name="to_details"
                    control={control}
                    label={ar.workOrders.fields.toDetails}
                    placeholder={ar.workOrders.fields.toDetailsPlaceholder}
                    onBlur={syncToLocation}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5 text-start">
                  <label className="text-sm font-semibold text-foreground-700 flex items-center gap-1 select-none">
                    <span>{ar.workOrders.fields.scheduledAt}</span>
                    <span className="text-default-400 text-xs font-normal ms-1">(اختياري)</span>
                  </label>
                  <Controller
                    name="scheduled_at"
                    control={control}
                    render={({ field }) => (
                      <Input
                        type="datetime-local"
                        value={field.value || ''}
                        onChange={field.onChange}
                        variant="bordered"
                        placeholder={ar.workOrders.fields.scheduledAt}
                        aria-label={ar.workOrders.fields.scheduledAt}
                        classNames={{
                          inputWrapper:
                            'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 focus-within:border-primary',
                        }}
                      />
                    )}
                  />
                </div>

                <FormInput
                  name="cargo_type"
                  control={control}
                  label={ar.workOrders.fields.cargoType}
                  placeholder={ar.workOrders.fields.cargoTypePlaceholder}
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                  name="pay_amount"
                  control={control}
                  type="number"
                  min="0"
                  step="any"
                  label={ar.workOrders.fields.payAmount}
                  placeholder={ar.workOrders.fields.payAmountPlaceholder}
                />
              </div>

              <FormTextarea
                name="notes"
                control={control}
                label={ar.workOrders.fields.notes}
                placeholder={ar.workOrders.fields.notesPlaceholder}
                minRows={3}
              />
            </CardBody>
          </Card>
        )}

        {/* STEP 2: Capacity */}
        {currentStep === 2 && (
          <Card className="shadow-sm">
            <CardHeader className="font-semibold text-lg pb-0">
              {ar.workOrders.steps.capacity}
            </CardHeader>
            <CardBody className="space-y-4 pt-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                  name="trips_needed"
                  control={control}
                  rules={{
                    required: ar.workOrders.validation.tripsNeededRequired,
                    min: { value: 1, message: ar.workOrders.validation.tripsNeededMin },
                  }}
                  type="number"
                  min="1"
                  label={ar.workOrders.fields.tripsNeeded}
                  isRequired
                />

                <FormInput
                  name="backup_needed"
                  control={control}
                  rules={{
                    min: { value: 0, message: ar.workOrders.validation.backupNeededMin },
                  }}
                  type="number"
                  min="0"
                  label={ar.workOrders.fields.backupNeeded}
                />
              </div>

              <div className="pt-2">
                <Controller
                  name="exclude_same_day_confirmed"
                  control={control}
                  render={({ field: { value, onChange } }) => (
                    <Checkbox isSelected={value} onValueChange={onChange}>
                      {ar.workOrders.fields.excludeSameDayConfirmed}
                    </Checkbox>
                  )}
                />
              </div>
            </CardBody>
          </Card>
        )}

        {/* STEP 3: Filters with Live Matching */}
        {currentStep === 3 && (
          <div className="space-y-4">
            <Card className="shadow-sm">
              <CardHeader className="flex justify-between items-center pb-0">
                <div className="flex flex-col gap-0.5">
                  <span className="font-semibold text-lg text-foreground-900">
                    {ar.workOrders.steps.filters}
                  </span>
                  <span className="text-xs text-default-500">
                    تحديد شروط ومعايير أسئلة السائقين وبيانات الأسطول لمطابقة المناسبين للرحلة
                  </span>
                </div>
                <Button
                  size="sm"
                  color="primary"
                  variant="flat"
                  startContent={<PlusIcon className="w-4 h-4" />}
                  onPress={() => {
                    const firstQ = filterQuestions[0];
                    let defaultVal: any = '';
                    if (firstQ?.key === 'whatsapp_group') {
                      defaultVal = whatsappGroups[0]?.id ? String(whatsappGroups[0].id) : '';
                    } else if (firstQ?.key === 'driver_experience') {
                      defaultVal = 'experienced_only';
                    } else if (firstQ?.type === 'yes_no') {
                      defaultVal = 'yes';
                    } else {
                      defaultVal = firstQ?.options?.[0]?.value || '';
                    }
                    append({
                      question_id: firstQ?.id || 1,
                      operator: 'eq',
                      value: defaultVal,
                    });
                  }}
                  isDisabled={filterQuestions.length === 0}
                  className="font-medium"
                >
                  {ar.workOrders.matching.addFilter}
                </Button>
              </CardHeader>

              <CardBody className="space-y-4 pt-4">
                {fields.length === 0 ? (
                  <div className="text-center py-8 px-4 border-2 border-dashed border-default-200 dark:border-default-800 rounded-xl bg-default-50/50">
                    <p className="text-sm font-medium text-default-600 mb-1">
                      {ar.workOrders.matching.noFiltersNotice}
                    </p>
                    <p className="text-xs text-default-400">
                      يمكنك إضافة شروط إضافية مثل نوع السيارة أو تجهيز التبريد لتضييق نطاق السائقين.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-3.5">
                    {fields.map((fieldItem, index) => {
                      const currentQId = watchedFilters?.[index]?.question_id ?? fieldItem.question_id;
                      const selectedQuestion = filterQuestions.find(
                        (q) => q.id === Number(currentQId)
                      );
                      const isYesNo = selectedQuestion?.type === 'yes_no';
                      const isChoice =
                        selectedQuestion?.type === 'single_choice' || isYesNo;
                      const isNumber = selectedQuestion?.type === 'number';

                      const isRefrig =
                        selectedQuestion?.key === 'has_refrigeration' ||
                        selectedQuestion?.text_ar?.includes('تبريد');

                      return (
                        <div
                          key={fieldItem.id}
                          className="p-4 rounded-xl border border-default-200 dark:border-default-800 bg-default-50/50 dark:bg-default-100/5 shadow-xs transition-all hover:border-default-300 space-y-3"
                        >
                          {/* Filter Item Top Bar */}
                          <div className="flex items-center justify-between pb-2 border-b border-default-200/60 dark:border-default-800/60">
                            <div className="flex items-center gap-2">
                              <Chip
                                size="sm"
                                variant="flat"
                                color="primary"
                                className="font-semibold text-xs h-6 px-2.5"
                              >
                                معيار #{index + 1}
                              </Chip>
                              {selectedQuestion && (
                                <span className="text-xs font-medium text-default-600 line-clamp-1">
                                  {selectedQuestion.text_ar}
                                </span>
                              )}
                            </div>

                            <Button
                              size="sm"
                              color="danger"
                              variant="light"
                              isIconOnly
                              onPress={() => remove(index)}
                              aria-label={ar.workOrders.matching.removeFilter}
                              className="text-danger hover:bg-danger-50 dark:hover:bg-danger-950/40 min-w-8 w-8 h-8 rounded-lg"
                              title={ar.workOrders.matching.removeFilter}
                            >
                              <TrashIcon className="w-4 h-4" />
                            </Button>
                          </div>

                          {/* Inputs Grid */}
                          <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                            {/* Question Select (5 cols) */}
                            <div className="md:col-span-5 flex flex-col gap-1.5 text-start">
                              <label className="text-xs font-semibold text-foreground-700">
                                {ar.workOrders.matching.selectQuestion}
                              </label>
                              <Controller
                                name={`filters.${index}.question_id`}
                                control={control}
                                render={({ field }) => (
                                  <Select
                                    aria-label={ar.workOrders.matching.selectQuestion}
                                    placeholder={ar.workOrders.matching.selectQuestion}
                                    variant="bordered"
                                    selectedKeys={field.value ? [String(field.value)] : []}
                                    onSelectionChange={(keys) => {
                                      const val = Array.from(keys)[0];
                                      if (val !== undefined) {
                                        const newQId = Number(val);
                                        field.onChange(newQId);
                                        const nextQ = filterQuestions.find((q) => q.id === newQId);
                                        const currentOp = watchedFilters?.[index]?.operator || 'eq';
                                        let defaultVal: any = '';
                                        if (nextQ?.key === 'whatsapp_group') {
                                          const gId = whatsappGroups[0]?.id ? String(whatsappGroups[0].id) : '';
                                          defaultVal = currentOp === 'in' ? (gId ? [gId] : []) : gId;
                                        } else if (nextQ?.key === 'driver_experience') {
                                          defaultVal = currentOp === 'in' ? ['experienced_only'] : 'experienced_only';
                                        } else if (nextQ?.type === 'yes_no') {
                                          setValue(`filters.${index}.operator`, 'eq', { shouldDirty: true, shouldTouch: true });
                                          defaultVal = 'yes';
                                        } else {
                                          const firstVal = nextQ?.options?.[0]?.value || '';
                                          defaultVal = currentOp === 'in' ? (firstVal ? [firstVal] : []) : firstVal;
                                        }
                                        setValue(`filters.${index}.value`, defaultVal, { shouldDirty: true, shouldTouch: true });
                                      }
                                    }}
                                    classNames={{
                                      base: 'w-full !mt-0',
                                      trigger:
                                        'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary',
                                    }}
                                  >
                                    {filterQuestions.map((q) => (
                                      <SelectItem key={String(q.id)} textValue={q.text_ar}>
                                        {q.text_ar}
                                      </SelectItem>
                                    ))}
                                  </Select>
                                )}
                              />
                            </div>

                            {/* Operator Select (3 cols) */}
                            <div className="md:col-span-3 flex flex-col gap-1.5 text-start">
                              <label className="text-xs font-semibold text-foreground-700">
                                {ar.workOrders.matching.selectOperator}
                              </label>
                              <Controller
                                name={`filters.${index}.operator`}
                                control={control}
                                render={({ field }) => (
                                  <Select
                                    aria-label={ar.workOrders.matching.selectOperator}
                                    placeholder={ar.workOrders.matching.selectOperator}
                                    variant="bordered"
                                    selectedKeys={field.value ? [field.value] : ['eq']}
                                    onSelectionChange={(keys) => {
                                      const newOp = (Array.from(keys)[0] as string) || 'eq';
                                      field.onChange(newOp);
                                      const currentVal = watchedFilters?.[index]?.value;
                                      if (newOp === 'in') {
                                        if (typeof currentVal === 'string' && currentVal !== '') {
                                          setValue(`filters.${index}.value`, [currentVal], { shouldDirty: true, shouldTouch: true });
                                        } else if (!Array.isArray(currentVal)) {
                                          setValue(`filters.${index}.value`, [], { shouldDirty: true, shouldTouch: true });
                                        }
                                      } else if (newOp === 'eq') {
                                        if (Array.isArray(currentVal)) {
                                          setValue(`filters.${index}.value`, currentVal[0] || '', { shouldDirty: true, shouldTouch: true });
                                        }
                                      }
                                    }}
                                    classNames={{
                                      base: 'w-full !mt-0',
                                      trigger:
                                        'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary',
                                    }}
                                  >
                                    {isChoice && !isYesNo ? (
                                      <>
                                        <SelectItem key="eq" textValue={ar.workOrders.matching.operatorEq}>
                                          {ar.workOrders.matching.operatorEq} (خيار محدد)
                                        </SelectItem>
                                        <SelectItem key="in" textValue={ar.workOrders.matching.operatorIn}>
                                          {ar.workOrders.matching.operatorIn} (أكثر من خيار)
                                        </SelectItem>
                                      </>
                                    ) : isYesNo ? (
                                      <SelectItem key="eq" textValue={ar.workOrders.matching.operatorEq}>
                                        {ar.workOrders.matching.operatorEq}
                                      </SelectItem>
                                    ) : isNumber ? (
                                      <>
                                        <SelectItem key="eq" textValue={ar.workOrders.matching.operatorEq}>
                                          {ar.workOrders.matching.operatorEq}
                                        </SelectItem>
                                        <SelectItem key="gte" textValue={ar.workOrders.matching.operatorGte}>
                                          {ar.workOrders.matching.operatorGte}
                                        </SelectItem>
                                        <SelectItem key="lte" textValue={ar.workOrders.matching.operatorLte}>
                                          {ar.workOrders.matching.operatorLte}
                                        </SelectItem>
                                      </>
                                    ) : (
                                      <SelectItem key="eq" textValue={ar.workOrders.matching.operatorEq}>
                                        {ar.workOrders.matching.operatorEq}
                                      </SelectItem>
                                    )}
                                  </Select>
                                )}
                              />
                            </div>

                            {/* Value Control (4 cols) */}
                            <div className="md:col-span-4 flex flex-col gap-1.5 text-start">
                              <label className="text-xs font-semibold text-foreground-700">
                                {ar.workOrders.matching.value}
                              </label>
                              <Controller
                                name={`filters.${index}.value`}
                                control={control}
                                render={({ field }) => {
                                  // 1. Yes/No Question
                                  if (isYesNo) {
                                    const yesNoOptions = [
                                      { key: 'yes', label: isRefrig ? 'نعم (مجهزة تبريد)' : 'نعم' },
                                      { key: 'no', label: isRefrig ? 'لا (غير مجهزة)' : 'لا' },
                                    ];
                                    return (
                                      <Select
                                        aria-label={ar.workOrders.matching.value}
                                        placeholder="اختر الإجابة المطلوبة..."
                                        variant="bordered"
                                        selectedKeys={
                                          field.value !== undefined && field.value !== ''
                                            ? [String(field.value)]
                                            : []
                                        }
                                        onSelectionChange={(keys) => {
                                          const val = Array.from(keys)[0];
                                          field.onChange(val !== undefined ? String(val) : '');
                                        }}
                                        classNames={{
                                          base: 'w-full !mt-0',
                                          trigger:
                                            'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary',
                                        }}
                                      >
                                        {yesNoOptions.map((opt) => (
                                          <SelectItem key={opt.key} textValue={opt.label}>
                                            {opt.label}
                                          </SelectItem>
                                        ))}
                                      </Select>
                                    );
                                  }

                                  // 2. Single Choice Question (including whatsapp_group and driver_experience)
                                  const isWhatsappGroup = selectedQuestion?.key === 'whatsapp_group';
                                  const isDriverExperience = selectedQuestion?.key === 'driver_experience';

                                  const choiceOptions = isWhatsappGroup
                                    ? (whatsappGroups && whatsappGroups.length > 0
                                        ? whatsappGroups.map((g) => ({ value: String(g.id), label_ar: g.name }))
                                        : selectedQuestion?.options || [])
                                    : isDriverExperience
                                    ? [
                                        { value: 'experienced_only', label_ar: ar.workOrders.driverProfileFilters.experienceExperienced },
                                        { value: 'new_only', label_ar: ar.workOrders.driverProfileFilters.experienceNew },
                                      ]
                                    : selectedQuestion?.options || [];

                                  if (isChoice && choiceOptions.length > 0) {
                                    const currentOp = watchedFilters?.[index]?.operator || 'eq';
                                    const isIn = currentOp === 'in';

                                    if (isIn) {
                                      const selectedKeysArray = Array.isArray(field.value)
                                        ? field.value.map(String)
                                        : field.value
                                        ? [String(field.value)]
                                        : [];

                                      return (
                                        <Select
                                          aria-label={ar.workOrders.matching.value}
                                          placeholder="اختر خياراً واحداً أو أكثر..."
                                          variant="bordered"
                                          selectionMode="multiple"
                                          selectedKeys={new Set(selectedKeysArray)}
                                          onSelectionChange={(keys) => {
                                            const arr = Array.from(keys).map(String);
                                            field.onChange(arr);
                                          }}
                                          classNames={{
                                            base: 'w-full !mt-0',
                                            trigger:
                                              'min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary py-1',
                                          }}
                                        >
                                          {choiceOptions.map((opt) => (
                                            <SelectItem key={opt.value} textValue={opt.label_ar}>
                                              {opt.label_ar}
                                            </SelectItem>
                                          ))}
                                        </Select>
                                      );
                                    }

                                    // Single choice (eq)
                                    const selectedKey = Array.isArray(field.value)
                                      ? (field.value[0] ? String(field.value[0]) : '')
                                      : field.value !== undefined && field.value !== ''
                                      ? String(field.value)
                                      : '';

                                    return (
                                      <Select
                                        aria-label={ar.workOrders.matching.value}
                                        placeholder="اختر الإجابة المطلوبة..."
                                        variant="bordered"
                                        selectedKeys={selectedKey ? [selectedKey] : []}
                                        onSelectionChange={(keys) => {
                                          const val = Array.from(keys)[0];
                                          field.onChange(val !== undefined ? String(val) : '');
                                        }}
                                        classNames={{
                                          base: 'w-full !mt-0',
                                          trigger:
                                            'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 data-[focus=true]:border-primary',
                                        }}
                                      >
                                        {choiceOptions.map((opt) => (
                                          <SelectItem key={opt.value} textValue={opt.label_ar}>
                                            {opt.label_ar}
                                          </SelectItem>
                                        ))}
                                      </Select>
                                    );
                                  }

                                  // 3. Number or Text Input
                                  return (
                                    <Input
                                      {...field}
                                      value={field.value ?? ''}
                                      aria-label={ar.workOrders.matching.value}
                                      placeholder={isNumber ? 'مثال: 5' : 'اكتب القيمة المطلوبة...'}
                                      type={isNumber ? 'number' : 'text'}
                                      variant="bordered"
                                      classNames={{
                                        inputWrapper:
                                          'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 focus-within:border-primary',
                                      }}
                                    />
                                  );
                                }}
                              />
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </CardBody>
            </Card>

            {/* System Filters (Rating, No-shows, Recency) */}
            <Card className="shadow-sm">
              <CardHeader className="font-semibold text-base pb-0">
                {ar.workOrders.systemFilters.title}
              </CardHeader>
              <CardBody className="space-y-4 pt-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <FormInput
                    name="min_avg_rating"
                    control={control}
                    type="number"
                    step="0.1"
                    min="1"
                    max="5"
                    label={ar.workOrders.systemFilters.minRating}
                    placeholder={ar.workOrders.systemFilters.minRatingPlaceholder}
                  />

                  <FormInput
                    name="max_no_shows"
                    control={control}
                    type="number"
                    min="0"
                    label={ar.workOrders.systemFilters.maxNoShows}
                    placeholder={ar.workOrders.systemFilters.maxNoShowsPlaceholder}
                  />

                  <FormInput
                    name="last_trip_within_days"
                    control={control}
                    type="number"
                    min="1"
                    label={ar.workOrders.systemFilters.lastTripDays}
                    placeholder={ar.workOrders.systemFilters.lastTripDaysPlaceholder}
                  />
                </div>

                <div>
                  <Controller
                    name="include_unrated"
                    control={control}
                    render={({ field: { value, onChange } }) => (
                      <Checkbox isSelected={value} onValueChange={onChange}>
                        {ar.workOrders.systemFilters.includeUnrated}
                      </Checkbox>
                    )}
                  />
                </div>
              </CardBody>
            </Card>

            {/* Live Matching Results Box */}
            <Card className="border border-primary-200 bg-primary-50/40 shadow-sm">
              <CardBody className="p-4 space-y-3">
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pb-2 border-b border-primary-200/50">
                  <div className="flex items-center gap-2">
                    <UsersIcon className="w-5 h-5 text-primary" />
                    <span className="font-semibold text-primary-900 text-sm sm:text-base">
                      {ar.workOrders.matching.countLabel}
                    </span>
                    {isMatchingLoading ? (
                      <div className="flex items-center gap-1.5 text-xs text-primary-600">
                        <Spinner size="sm" />
                        <span>{ar.workOrders.matching.calculating}</span>
                      </div>
                    ) : (
                      <Chip color="primary" variant="solid" className="font-bold text-sm">
                        {matchPreview?.count ?? 0} {ar.workOrders.matching.matchedDrivers}
                      </Chip>
                    )}
                  </div>

                  {/* Quick Phone Search */}
                  <div className="w-full sm:w-72">
                    <Controller
                      name="driver_phone_filter"
                      control={control}
                      render={({ field }) => (
                        <Input
                          {...field}
                          value={field.value ?? ''}
                          size="sm"
                          placeholder={ar.workOrders.driverProfileFilters.phonePlaceholder}
                          aria-label={ar.workOrders.driverProfileFilters.phoneLabel}
                          startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />}
                          variant="bordered"
                          dir="ltr"
                          isClearable
                          onClear={() => field.onChange('')}
                          classNames={{
                            inputWrapper:
                              'h-9 min-h-9 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 focus-within:border-primary',
                          }}
                        />
                      )}
                    />
                  </div>
                </div>

                {matchPreview && matchPreview.count === 0 && !isMatchingLoading && (
                  <div className="flex items-center gap-2 text-warning-700 bg-warning-50 p-2.5 rounded-lg text-xs">
                    <ExclamationCircleIcon className="w-4 h-4 shrink-0 text-warning-600" />
                    <span>{ar.workOrders.matching.noDrivers}</span>
                  </div>
                )}

                {matchPreview && matchPreview.drivers?.length > 0 && (
                  <div className="pt-2">
                    <span className="text-xs font-semibold text-default-600">
                      {ar.workOrders.matching.driversPreview}
                    </span>
                    <div className="flex flex-wrap gap-1.5 mt-2">
                      {matchPreview.drivers.map((drv) => (
                        <Chip key={drv.id} size="sm" variant="bordered" className="text-xs">
                          {drv.name || 'كابتن'} ({drv.phone})
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}
              </CardBody>
            </Card>
          </div>
        )}

        {/* STEP 4: Send Strategy & WhatsApp Message */}
        {currentStep === 4 && (
          <div className="space-y-6">
            <Card className="shadow-sm">
              <CardHeader className="font-semibold text-lg pb-0">
                {ar.workOrders.steps.strategy}
              </CardHeader>
              <CardBody className="space-y-4 pt-4">
                <Controller
                  name="send_strategy"
                  control={control}
                  render={({ field: { value, onChange } }) => (
                    <RadioGroup
                      label={ar.workOrders.fields.sendStrategy}
                      value={value}
                      onValueChange={onChange}
                    >
                      <Radio value="all">
                        {ar.workOrders.fields.strategyAll}
                      </Radio>
                      <Radio value="staged">
                        {ar.workOrders.fields.strategyStaged}
                      </Radio>
                    </RadioGroup>
                  )}
                />

                {watchedStrategy === 'staged' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-default-50 rounded-lg">
                    <FormInput
                      name="batch_size"
                      control={control}
                      rules={{
                        required: ar.workOrders.validation.batchSizeRequired,
                        min: { value: 1, message: ar.workOrders.validation.batchSizeMin },
                      }}
                      type="number"
                      min="1"
                      label={ar.workOrders.fields.batchSize}
                      isRequired
                    />

                    <FormInput
                      name="batch_interval_minutes"
                      control={control}
                      rules={{
                        required: ar.workOrders.validation.batchIntervalRequired,
                        min: { value: 1, message: ar.workOrders.validation.batchIntervalMin },
                      }}
                      type="number"
                      min="1"
                      label={ar.workOrders.fields.batchIntervalMinutes}
                      isRequired
                    />
                  </div>
                )}
              </CardBody>
            </Card>

            {/* Scheduled Dispatch & Offer Expiration */}
            <Card className="shadow-sm">
              <CardHeader className="font-semibold text-lg pb-0 flex items-center justify-between">
                <span>الجدولة التلقائية وتحديد مهلة الرد (اختياري)</span>
              </CardHeader>
              <CardBody className="space-y-4 pt-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="flex flex-col gap-1.5 text-start">
                    <label className="text-sm font-semibold text-foreground-700 flex items-center gap-1 select-none">
                      <span>{ar.workOrders.fields.scheduledDispatchAt}</span>
                      <span className="text-default-400 text-xs font-normal ms-1">(اختياري)</span>
                    </label>
                    <Controller
                      name="scheduled_dispatch_at"
                      control={control}
                      render={({ field }) => (
                        <Input
                          type="datetime-local"
                          value={field.value || ''}
                          onChange={field.onChange}
                          variant="bordered"
                          placeholder={ar.workOrders.fields.scheduledDispatchAtPlaceholder}
                          aria-label={ar.workOrders.fields.scheduledDispatchAt}
                          classNames={{
                            inputWrapper:
                              'h-10 min-h-10 border-1.5 border-default-300 dark:border-default-700 bg-background shadow-xs hover:border-default-400 focus-within:border-primary',
                          }}
                        />
                      )}
                    />
                    <span className="text-[11px] text-default-400">
                      {ar.workOrders.fields.scheduledDispatchAtPlaceholder}
                    </span>
                  </div>

                  <div className="flex flex-col gap-1.5 text-start">
                    <FormInput
                      name="offer_expires_in_minutes"
                      control={control}
                      type="number"
                      min="1"
                      label={ar.workOrders.fields.offerExpiresInMinutes}
                      placeholder={ar.workOrders.fields.offerExpiresInMinutesPlaceholder}
                    />
                    <span className="text-[11px] text-default-400">
                      {ar.workOrders.fields.offerExpiresNotice}
                    </span>
                  </div>
                </div>
              </CardBody>
            </Card>

            {/* Custom WhatsApp Message Card */}
            <Card className="shadow-sm">
              <CardHeader className="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-0">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 rounded-xl">
                    <ChatBubbleLeftRightIcon className="h-5 w-5" />
                  </div>
                  <div className="flex flex-col text-start">
                    <span className="font-semibold text-lg text-foreground-900">
                      {ar.workOrders.messageTemplate.title}
                    </span>
                    <span className="text-xs text-default-500">
                      {ar.workOrders.messageTemplate.description}
                    </span>
                  </div>
                </div>

                <Button
                  size="sm"
                  variant="flat"
                  color="default"
                  startContent={<ArrowPathIcon className="h-3.5 w-3.5" />}
                  onPress={handleResetMessage}
                  className="font-semibold text-xs self-start sm:self-auto"
                >
                  {ar.workOrders.messageTemplate.resetToDefault}
                </Button>
              </CardHeader>

              <CardBody className="p-4 sm:p-6 space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                  {/* Left Column: Editor & Variable Picker (7 cols) */}
                  <div className="lg:col-span-7 space-y-3.5 text-start">
                    {/* Variables Toolbar */}
                    <div className="space-y-2 p-3 bg-default-50 dark:bg-default-100/50 rounded-xl border border-default-200">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-foreground-800">
                          {ar.workOrders.messageTemplate.variablesTitle}
                        </span>
                        <span className="text-[11px] text-default-400">
                          (انقر للإدراج)
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {MESSAGE_VARIABLES.map((v) => (
                          <Button
                            key={v.tag}
                            size="sm"
                            variant="flat"
                            color="primary"
                            className="text-xs h-7 px-2 font-medium bg-primary-50 hover:bg-primary-100 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"
                            onPress={() => insertVariable(v.tag)}
                          >
                            + {v.label}
                          </Button>
                        ))}
                      </div>
                    </div>

                    {/* Textarea */}
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold text-foreground-700">
                        {ar.workOrders.messageTemplate.customMessageLabel}
                      </label>
                      <Controller
                        name="custom_message"
                        control={control}
                        render={({ field }) => (
                          <textarea
                            {...field}
                            ref={(el) => {
                              field.ref(el);
                              messageTextareaRef.current = el;
                            }}
                            dir="rtl"
                            rows={8}
                            placeholder={ar.workOrders.messageTemplate.customMessagePlaceholder}
                            className="w-full p-3.5 rounded-xl border-1.5 border-default-300 dark:border-default-700 bg-background text-foreground font-sans text-xs sm:text-sm leading-relaxed focus:outline-none focus:border-primary transition-colors resize-y min-h-[200px]"
                          />
                        )}
                      />
                      <div className="flex items-center justify-between text-[11px] text-default-400 px-1">
                        <span>
                          سيتم استبدال المتغيرات المحصورة بين {'{{ }}'} تلقائياً ببيانات الرحلة والسائق.
                        </span>
                        <span>{(watchedCustomMessage || '').length} حرف</span>
                      </div>
                    </div>
                  </div>

                  {/* Right Column: WhatsApp Live Preview (5 cols) */}
                  <div className="lg:col-span-5 flex flex-col items-center justify-start space-y-2">
                    <span className="text-xs font-bold text-foreground-800 self-start">
                      {ar.workOrders.messageTemplate.previewTitle}
                    </span>
                    {renderWhatsAppPreview(watchedCustomMessage || DEFAULT_OFFER_MESSAGE)}
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>
        )}

        {/* STEP 5: Review */}
        {currentStep === 5 && (() => {
          const reviewValues = getValues();
          return (
            <Card className="shadow-sm">
              <CardHeader className="font-semibold text-lg pb-0">
                {ar.workOrders.steps.review}
              </CardHeader>
              <CardBody className="space-y-4 pt-4">
                {/* Summary of details */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-default-50 p-4 rounded-lg">
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.fields.fromLocation}:</span>
                    <span className="font-semibold">{reviewValues.from_location || '—'}</span>
                  </div>
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.fields.toLocation}:</span>
                    <span className="font-semibold">{reviewValues.to_location || '—'}</span>
                  </div>
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.fields.scheduledAt}:</span>
                    <span className="font-semibold">{reviewValues.scheduled_at || '—'}</span>
                  </div>
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.fields.payAmount}:</span>
                    <span className="font-semibold">
                      {reviewValues.pay_amount ? `${reviewValues.pay_amount} ج.م` : '—'}
                    </span>
                  </div>
                  <div>
                    <span className="text-default-500 block">المطلوب (أساسي + احتياطي):</span>
                    <span className="font-semibold text-primary">
                      {reviewValues.trips_needed} أساسي + {reviewValues.backup_needed || 0} احتياطي
                    </span>
                  </div>
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.fields.sendStrategy}:</span>
                    <span className="font-semibold">
                      {reviewValues.send_strategy === 'all'
                        ? ar.workOrders.fields.strategyAll
                        : `${ar.workOrders.fields.strategyStaged} (${reviewValues.batch_size} كل ${reviewValues.batch_interval_minutes} دقيقة)`}
                    </span>
                  </div>
                  {reviewValues.scheduled_dispatch_at && (
                    <div>
                      <span className="text-default-500 block">{ar.workOrders.fields.scheduledDispatchAt}:</span>
                      <span className="font-semibold text-primary">
                        {reviewValues.scheduled_dispatch_at}
                      </span>
                    </div>
                  )}
                  {reviewValues.offer_expires_in_minutes && (
                    <div>
                      <span className="text-default-500 block">{ar.workOrders.fields.offerExpiresInMinutes}:</span>
                      <span className="font-semibold text-warning-700">
                        {reviewValues.offer_expires_in_minutes} دقيقة
                      </span>
                    </div>
                  )}
                  <div>
                    <span className="text-default-500 block">{ar.workOrders.matching.countLabel}</span>
                    <span className="font-bold text-success">
                      {matchPreview?.count ?? 0} {ar.workOrders.matching.matchedDrivers}
                    </span>
                  </div>

                  {(reviewValues.min_avg_rating ||
                    reviewValues.max_no_shows !== '' ||
                    reviewValues.last_trip_within_days) && (
                    <div className="md:col-span-2 border-t border-default-200 pt-2">
                      <span className="text-default-500 block font-semibold mb-1">
                        {ar.workOrders.systemFilters.title}:
                      </span>
                      <div className="flex flex-wrap gap-2 text-xs">
                        {reviewValues.min_avg_rating && (
                          <span className="bg-default-100 px-2.5 py-1 rounded">
                            {ar.workOrders.systemFilters.minRating}: {reviewValues.min_avg_rating} ⭐
                          </span>
                        )}
                        {reviewValues.max_no_shows !== '' &&
                          reviewValues.max_no_shows !== undefined && (
                            <span className="bg-default-100 px-2.5 py-1 rounded">
                              {ar.workOrders.systemFilters.maxNoShows}: {reviewValues.max_no_shows}
                            </span>
                          )}
                        {reviewValues.last_trip_within_days && (
                          <span className="bg-default-100 px-2.5 py-1 rounded">
                            {ar.workOrders.systemFilters.lastTripDays}: {reviewValues.last_trip_within_days} يوم
                          </span>
                        )}
                        {reviewValues.include_unrated && (
                          <span className="bg-default-100 px-2.5 py-1 rounded">
                            {ar.workOrders.systemFilters.includeUnrated}
                          </span>
                        )}
                      </div>
                    </div>
                  )}

                  {reviewValues.filters && reviewValues.filters.length > 0 && (
                    <div className="md:col-span-2 border-t border-default-200 pt-2">
                      <span className="text-default-500 block font-semibold mb-1">
                        {ar.workOrders.steps.filters}:
                      </span>
                      <div className="flex flex-wrap gap-2 text-xs">
                        {(reviewValues.filters as any[]).map((f: any, i: number) => {
                          const q = filterQuestions.find((item) => item.id === Number(f.question_id));
                          if (!q) return null;
                          let valDisplay = '';
                          if (q.key === 'whatsapp_group') {
                            const ids = Array.isArray(f.value) ? f.value : [f.value];
                            valDisplay = ids
                              .map((id: any) => whatsappGroups.find((g) => String(g.id) === String(id))?.name || id)
                              .join('، ');
                          } else if (q.key === 'driver_experience') {
                            const vals = Array.isArray(f.value) ? f.value : [f.value];
                            valDisplay = vals
                              .map((v: any) =>
                                v === 'new_only'
                                  ? ar.workOrders.driverProfileFilters.experienceNew
                                  : ar.workOrders.driverProfileFilters.experienceExperienced
                              )
                              .join('، ');
                          } else if (q.type === 'yes_no') {
                            valDisplay = f.value === 'yes' ? 'نعم' : 'لا';
                          } else {
                            const vals = Array.isArray(f.value) ? f.value : [f.value];
                            valDisplay = vals
                              .map((v: any) => q.options?.find((opt) => opt.value === v)?.label_ar || v)
                              .join('، ');
                          }
                          return (
                            <span key={i} className="bg-default-100 text-default-800 px-2.5 py-1 rounded">
                              <strong>{q.text_ar}:</strong> {valDisplay} ({f.operator === 'in' ? 'ضمن الخيارات' : 'يساوي'})
                            </span>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>

                {reviewValues.notes && (
                  <div className="text-sm bg-default-50 p-3 rounded-lg">
                    <span className="text-default-500 block">{ar.workOrders.fields.notes}:</span>
                    <span>{reviewValues.notes}</span>
                  </div>
                )}

                {/* WhatsApp Message Preview in Step 5 */}
                <div className="border-t border-default-200 pt-4 space-y-3">
                  <div className="flex items-center gap-2">
                    <ChatBubbleLeftRightIcon className="w-5 h-5 text-emerald-600" />
                    <span className="font-semibold text-base text-foreground-900">
                      {ar.workOrders.messageTemplate.previewTitle}
                    </span>
                  </div>
                  <div className="flex justify-center p-2">
                    {renderWhatsAppPreview(watchedCustomMessage || DEFAULT_OFFER_MESSAGE)}
                  </div>
                </div>
              </CardBody>
            </Card>
          );
        })()}

        {/* Action Buttons Footer */}
        <div className="sticky bottom-0 z-30 -mx-3.5 sm:-mx-6 lg:-mx-8 -mb-4 sm:-mb-6 lg:-mb-8 mt-6 border-t border-default-200 bg-background/95 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-3.5 safe-pb-sticky shadow-lg shadow-black/5 flex items-center justify-between gap-3">
          <div>
            {currentStep > 1 && (
              <Button
                type="button"
                variant="flat"
                className="min-h-[44px]"
                startContent={<ArrowRightIcon className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />}
                onPress={handlePrev}
                isDisabled={isSubmitting}
              >
                {ar.common.previous}
              </Button>
            )}
          </div>

          <div className="flex items-center gap-2">
            {currentStep < STEPS.length ? (
              <Button
                type="button"
                color="primary"
                className="min-h-[44px]"
                endContent={<ArrowLeftIcon className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />}
                onPress={handleNext}
              >
                {ar.common.next}
              </Button>
            ) : (
              <>
                <Button
                  type="submit"
                  variant="flat"
                  className="min-h-[44px]"
                  startContent={<DocumentArrowDownIcon className="w-4 h-4" />}
                  onPress={() => setSubmitMode('draft')}
                  isLoading={isSubmitting && submitMode === 'draft'}
                  isDisabled={isSubmitting}
                >
                  {ar.workOrders.saveDraft}
                </Button>
                <Button
                  type="submit"
                  color="primary"
                  className="min-h-[44px]"
                  startContent={<PaperAirplaneIcon className="w-4 h-4" />}
                  onPress={() => setSubmitMode('send')}
                  isLoading={isSubmitting && submitMode === 'send'}
                  isDisabled={isSubmitting}
                >
                  {ar.workOrders.sendOrder}
                </Button>
              </>
            )}
          </div>
        </div>
      </form>
    </PageContainer>
  );
};

export default CreateWorkOrderPage;
