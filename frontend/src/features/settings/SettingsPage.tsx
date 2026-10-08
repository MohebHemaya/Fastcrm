import React, { useState, useEffect } from 'react';
import {
  Tabs,
  Tab,
  Button,
  Card,
  CardBody,
  Chip,
  Switch,
  Input,
  Spinner,
} from '@heroui/react';
import {
  QuestionMarkCircleIcon,
  Cog6ToothIcon,
  PlusIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  PencilSquareIcon,
  TrashIcon,
  FunnelIcon,
  CheckCircleIcon,
  CircleStackIcon,
  CheckIcon,
  ClockIcon,
  PaperAirplaneIcon,
} from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { useUiStore } from '@/lib/stores/useUiStore';
import { questionService } from '@/services/questionService';
import { settingsService } from '@/services/settingsService';
import type { Question } from '@/types/question';
import type { SystemSettings } from '@/types/settings';
import QuestionModal from './QuestionModal';

export const SettingsPage: React.FC = () => {
  const [selectedTab, setSelectedTab] = useState<string>('questions');

  // Questions State
  const [questions, setQuestions] = useState<Question[]>([]);
  const [isLoadingQuestions, setIsLoadingQuestions] = useState(true);
  const [isQuestionModalOpen, setIsQuestionModalOpen] = useState(false);
  const [selectedQuestion, setSelectedQuestion] = useState<Question | null>(null);
  const [deletingQuestion, setDeletingQuestion] = useState<Question | null>(null);

  // Settings State
  const [settings, setSettings] = useState<SystemSettings>({
    questionnaire_reminder_hours: 24,
    questionnaire_timeout_days: 3,
    send_rate_per_minute: 30,
    invite_batch_size: 50,
    invite_no_response_days: 3,
  });
  const [isLoadingSettings, setIsLoadingSettings] = useState(true);
  const [isSavingSettings, setIsSavingSettings] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  const fetchQuestions = async () => {
    setIsLoadingQuestions(true);
    try {
      const data = await questionService.getQuestions();
      setQuestions(data.filter((q) => !q.is_system));
    } catch {
      addToast({ type: 'error', message: 'تعذر تحميل الأسئلة.' });
    } finally {
      setIsLoadingQuestions(false);
    }
  };

  const fetchSettings = async () => {
    setIsLoadingSettings(true);
    try {
      const data = await settingsService.getSettings();
      setSettings(data.settings);
    } catch {
      addToast({ type: 'error', message: 'تعذر تحميل الإعدادات العامة.' });
    } finally {
      setIsLoadingSettings(false);
    }
  };

  useEffect(() => {
    fetchQuestions();
    fetchSettings();
  }, []);

  // Question Actions
  const handleToggleActive = async (q: Question) => {
    try {
      const res = await questionService.toggleActive(q.id);
      setQuestions((prev) =>
        prev.map((item) => (item.id === q.id ? { ...item, is_active: res.is_active } : item))
      );
      addToast({
        type: 'success',
        message: res.is_active ? 'تم تفعيل السؤال' : 'تم تعطيل السؤال',
      });
    } catch {
      addToast({ type: 'error', message: 'تعذر تغيير حالة السؤال.' });
    }
  };

  const handleMoveQuestion = async (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= questions.length) return;

    const newQuestions = [...questions];
    const temp = newQuestions[index];
    newQuestions[index] = newQuestions[targetIdx];
    newQuestions[targetIdx] = temp;

    // Recalculate sort_order
    const orders = newQuestions.map((q, idx) => ({ id: q.id, sort_order: idx + 1 }));
    setQuestions(newQuestions);

    try {
      await questionService.reorderQuestions(orders);
      addToast({ type: 'success', message: 'تم تحديث ترتيب الأسئلة.' });
    } catch {
      addToast({ type: 'error', message: 'تعذر حفظ ترتيب الأسئلة.' });
      fetchQuestions();
    }
  };

  const handleSaveQuestion = async (payload: any) => {
    if (selectedQuestion) {
      await questionService.updateQuestion(selectedQuestion.id, payload);
      addToast({ type: 'success', message: 'تم تحديث السؤال بنجاح.' });
    } else {
      await questionService.createQuestion(payload);
      addToast({ type: 'success', message: 'تم إضافة السؤال بنجاح.' });
    }
    fetchQuestions();
  };

  const handleDeactivateConfirm = async () => {
    if (!deletingQuestion) return;
    try {
      await questionService.deleteQuestion(deletingQuestion.id);
      addToast({ type: 'success', message: 'تم تعطيل السؤال بنجاح.' });
      fetchQuestions();
    } catch {
      addToast({ type: 'error', message: 'تعذر تعطيل السؤال.' });
    } finally {
      setDeletingQuestion(null);
    }
  };

  // General Settings Actions
  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSavingSettings(true);
    try {
      await settingsService.updateSettings(settings);
      addToast({ type: 'success', message: 'تم حفظ إعدادات النظام بنجاح.' });
    } catch {
      addToast({ type: 'error', message: 'تعذر حفظ الإعدادات.' });
    } finally {
      setIsSavingSettings(false);
    }
  };

  const filterQuestionsCount = questions.filter((q) => q.is_filter).length;
  const activeQuestionsCount = questions.filter((q) => q.is_active).length;

  return (
    <div className="space-y-6">
      <PageHeader
        title="الإعدادات والأسئلة"
        description="إدارة أسئلة استبيان واتساب، إصدارات الأسئلة، وإعدادات النظام ومعدلات الإرسال"
      />

      {/* Tabs */}
      <Tabs
        aria-label="خيارات الإعدادات"
        selectedKey={selectedTab}
        onSelectionChange={(key) => setSelectedTab(String(key))}
        color="primary"
        variant="solid"
        disableAnimation
        classNames={{
          tabList: 'bg-default-100 p-1.5 rounded-xl whitespace-nowrap gap-1 border border-default-200/50',
          tab: 'data-[selected=true]:bg-primary data-[selected=true]:text-primary-foreground font-semibold text-xs px-4 py-2 rounded-lg transition-colors',
        }}
      >
        <Tab
          key="questions"
          title={
            <div className="flex items-center gap-2">
              <QuestionMarkCircleIcon className="w-4 h-4" />
              <span>الأسئلة والاستبيان ({questions.length})</span>
            </div>
          }
        >
          <div className="space-y-6 mt-4">
            {/* Summary KPI Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <Card shadow="sm" className="border border-divider">
                <CardBody className="p-4 flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-primary/10 text-primary">
                    <QuestionMarkCircleIcon className="w-5 h-5" />
                  </div>
                  <div>
                    <p className="text-xs text-default-500 font-medium">إجمالي الأسئلة</p>
                    <p className="text-xl font-bold">{questions.length}</p>
                  </div>
                </CardBody>
              </Card>

              <Card shadow="sm" className="border border-divider">
                <CardBody className="p-4 flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <CheckCircleIcon className="w-5 h-5" />
                  </div>
                  <div>
                    <p className="text-xs text-default-500 font-medium">أسئلة نشطة</p>
                    <p className="text-xl font-bold text-emerald-600 dark:text-emerald-400">{activeQuestionsCount}</p>
                  </div>
                </CardBody>
              </Card>

              <Card shadow="sm" className="border border-divider">
                <CardBody className="p-4 flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-secondary/10 text-secondary">
                    <FunnelIcon className="w-5 h-5" />
                  </div>
                  <div>
                    <p className="text-xs text-default-500 font-medium">تستخدم كفلتر</p>
                    <p className="text-xl font-bold">{filterQuestionsCount}</p>
                  </div>
                </CardBody>
              </Card>

              <Card shadow="sm" className="border border-divider">
                <CardBody className="p-4 flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-warning/10 text-warning">
                    <CircleStackIcon className="w-5 h-5" />
                  </div>
                  <div>
                    <p className="text-xs text-default-500 font-medium">إجمالي الإجابات</p>
                    <p className="text-xl font-bold">
                      {questions.reduce((sum, q) => sum + (q.answers_count || 0), 0)}
                    </p>
                  </div>
                </CardBody>
              </Card>
            </div>

            {/* Questions Header & Add Button */}
            <div className="flex items-center justify-between flex-wrap gap-4">
              <div>
                <h3 className="text-lg font-bold">أسئلة الاستبيان للسائقين</h3>
                <p className="text-xs text-default-500">
                  ترسل هذه الأسئلة آلياً للسائق بمجرد موافقته على الدعوة (مهتم) بالترتيب الموضح أدناه
                </p>
              </div>
              <Button
                color="primary"
                startContent={<PlusIcon className="w-4 h-4" />}
                onPress={() => {
                  setSelectedQuestion(null);
                  setIsQuestionModalOpen(true);
                }}
              >
                إضافة سؤال جديد
              </Button>
            </div>

            {/* Questions List */}
            {isLoadingQuestions ? (
              <div className="flex justify-center p-12">
                <Spinner label="جاري تحميل الأسئلة..." />
              </div>
            ) : questions.length === 0 ? (
              <Card className="border border-dashed border-divider p-8 text-center">
                <CardBody className="flex flex-col items-center justify-center gap-3">
                  <QuestionMarkCircleIcon className="w-12 h-12 text-default-300" />
                  <p className="text-base font-semibold">لا توجد أسئلة مضافة بعد</p>
                  <p className="text-xs text-default-500 max-w-sm">
                    أضف أسئلتك الأولى لبدء استبيان السائقين وجمع معلومات سياراتهم وخبراتهم تلقائياً
                  </p>
                  <Button
                    color="primary"
                    variant="flat"
                    onPress={() => {
                      setSelectedQuestion(null);
                      setIsQuestionModalOpen(true);
                    }}
                  >
                    إضافة أول سؤال
                  </Button>
                </CardBody>
              </Card>
            ) : (
              <div className="space-y-3">
                {questions.map((q, idx) => (
                  <Card
                    key={q.id}
                    className={`border transition-all ${
                      q.is_active ? 'border-divider bg-content1' : 'border-divider/50 bg-content2/40 opacity-70'
                    }`}
                  >
                    <CardBody className="p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                      {/* Left: Reorder & Info */}
                      <div className="flex items-start gap-3">
                        <div className="flex flex-col gap-1 items-center justify-center pt-1">
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            onPress={() => handleMoveQuestion(idx, 'up')}
                            isDisabled={idx === 0}
                          >
                            <ChevronUpIcon className="w-3.5 h-3.5" />
                          </Button>
                          <span className="text-xs font-bold text-default-400">{idx + 1}</span>
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            onPress={() => handleMoveQuestion(idx, 'down')}
                            isDisabled={idx === questions.length - 1}
                          >
                            <ChevronDownIcon className="w-3.5 h-3.5" />
                          </Button>
                        </div>

                        <div className="space-y-1.5 text-start">
                          <div className="flex items-center gap-2 flex-wrap">
                            <h4 className="text-sm font-bold text-foreground">{q.text_ar}</h4>
                            <Chip size="sm" variant="flat" color="primary">
                              {q.type_label}
                            </Chip>
                            <Chip size="sm" variant="flat" color="default">
                              v{q.version}
                            </Chip>
                            {q.is_filter && (
                              <Chip
                                size="sm"
                                variant="flat"
                                color="secondary"
                                startContent={<FunnelIcon className="w-3 h-3" />}
                              >
                                فلتر
                              </Chip>
                            )}
                            {q.is_required && (
                              <Chip size="sm" variant="dot" color="danger">
                                إجباري
                              </Chip>
                            )}
                          </div>

                          <div className="flex items-center gap-3 text-xs text-default-500 flex-wrap">
                            <span className="font-mono bg-default-100 px-1.5 py-0.5 rounded text-[11px]">
                              {q.key}
                            </span>
                            {q.options_count > 0 && (
                              <span>
                                {q.options_count} خيارات ({q.options.map((o) => o.label_ar).join('، ')})
                              </span>
                            )}
                            <span>{q.answers_count} إجابة مسجلة</span>
                            {q.versions_count > 1 && (
                              <span className="text-primary-500 font-medium">
                                ({q.versions_count} إصدارات سابقة)
                              </span>
                            )}
                          </div>
                        </div>
                      </div>

                      {/* Right: Actions */}
                      <div className="flex items-center gap-3 self-end md:self-center">
                        <div className="flex items-center gap-1.5">
                          <span
                            className={`text-xs font-semibold ${
                              q.is_active
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-default-400'
                            }`}
                          >
                            {q.is_active ? 'نشط' : 'معطل'}
                          </span>
                          <Switch
                            size="sm"
                            color="success"
                            isSelected={q.is_active}
                            onValueChange={() => handleToggleActive(q)}
                          />
                        </div>

                        <Button
                          isIconOnly
                          size="sm"
                          variant="flat"
                          color="primary"
                          onPress={() => {
                            setSelectedQuestion(q);
                            setIsQuestionModalOpen(true);
                          }}
                        >
                          <PencilSquareIcon className="w-3.5 h-3.5" />
                        </Button>

                        <Button
                          isIconOnly
                          size="sm"
                          variant="flat"
                          color="danger"
                          onPress={() => setDeletingQuestion(q)}
                        >
                          <TrashIcon className="w-3.5 h-3.5" />
                        </Button>
                      </div>
                    </CardBody>
                  </Card>
                ))}
              </div>
            )}
          </div>
        </Tab>

        {/* General Settings Tab */}
        <Tab
          key="general"
          title={
            <div className="flex items-center gap-2">
              <Cog6ToothIcon className="w-4 h-4" />
              <span>إعدادات النظام العامة</span>
            </div>
          }
        >
          <div className="mt-4 max-w-3xl">
            {isLoadingSettings ? (
              <div className="flex justify-center p-12">
                <Spinner label="جاري تحميل الإعدادات..." />
              </div>
            ) : (
              <Card className="border border-divider">
                <form onSubmit={handleSaveSettings}>
                  <CardBody className="p-6 space-y-6 text-start">
                    <div>
                      <h3 className="text-base font-bold flex items-center gap-2">
                        <ClockIcon className="w-5 h-5 text-primary" />
                        <span>مواعيد ومهلات الاستبيان والدعوات</span>
                      </h3>
                      <p className="text-xs text-default-500 mt-1">
                        التحكم في مهلة التذكير التلقائي ومهلات انتهاء الدعوات وتحويل السائقين غير المتجاوبين
                      </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <Input
                        type="number"
                        label="مهلة تذكير الاستبيان (ساعات)"
                        labelPlacement="outside"
                        variant="bordered"
                        dir="ltr"
                        value={String(settings.questionnaire_reminder_hours)}
                        onValueChange={(val) =>
                          setSettings({ ...settings, questionnaire_reminder_hours: Number(val) })
                        }
                        description="يرسل النظام تذكيراً واحداً بعد هذه المدة من عدم إكمال الاستبيان"
                        isRequired
                        min={1}
                        max={168}
                      />

                      <Input
                        type="number"
                        label="مهلة انتهاء الاستبيان (أيام)"
                        labelPlacement="outside"
                        variant="bordered"
                        dir="ltr"
                        value={String(settings.questionnaire_timeout_days)}
                        onValueChange={(val) =>
                          setSettings({ ...settings, questionnaire_timeout_days: Number(val) })
                        }
                        description="يتحول السائق تلقائياً إلى حالة 'لم يرد' بعد هذه الأيام"
                        isRequired
                        min={1}
                        max={30}
                      />

                      <Input
                        type="number"
                        label="مهلة عدم الرد على الدعوة (أيام)"
                        labelPlacement="outside"
                        variant="bordered"
                        dir="ltr"
                        value={String(settings.invite_no_response_days)}
                        onValueChange={(val) =>
                          setSettings({ ...settings, invite_no_response_days: Number(val) })
                        }
                        description="تنتهي صلاحية الدعوة الأولية ويتحول السائق إلى 'لم يرد'"
                        isRequired
                        min={1}
                        max={30}
                      />

                      <Input
                        type="number"
                        label="حجم دفعة إرسال الدعوات"
                        labelPlacement="outside"
                        variant="bordered"
                        dir="ltr"
                        value={String(settings.invite_batch_size)}
                        onValueChange={(val) =>
                          setSettings({ ...settings, invite_batch_size: Number(val) })
                        }
                        description="عدد السائقين المدعوين في كل دفعة مجمعة"
                        isRequired
                        min={1}
                        max={500}
                      />
                    </div>

                    <div className="pt-4 border-t border-divider">
                      <h3 className="text-base font-bold flex items-center gap-2">
                        <PaperAirplaneIcon className="w-5 h-5 text-emerald-500 rtl:-scale-x-100" />
                        <span>معدلات إرسال رسائل واتساب</span>
                      </h3>
                      <p className="text-xs text-default-500 mt-1">
                        تحديد سقف سرعة الإرسال لتفادي حظر الرقم من قبل واتساب
                      </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <Input
                        type="number"
                        label="أقصى معدل إرسال بالدقيقة"
                        labelPlacement="outside"
                        variant="bordered"
                        dir="ltr"
                        value={String(settings.send_rate_per_minute)}
                        onValueChange={(val) =>
                          setSettings({ ...settings, send_rate_per_minute: Number(val) })
                        }
                        description="عدد الرسائل القصوى المرسلة في الدقيقة الواحدة (الافتراضي 30)"
                        isRequired
                        min={1}
                        max={300}
                      />
                    </div>

                    <div className="flex justify-end pt-4 border-t border-divider">
                      <Button
                        type="submit"
                        color="primary"
                        isLoading={isSavingSettings}
                        startContent={!isSavingSettings && <CheckIcon className="w-4 h-4" />}
                      >
                        حفظ الإعدادات
                      </Button>
                    </div>
                  </CardBody>
                </form>
              </Card>
            )}
          </div>
        </Tab>
      </Tabs>

      {/* Question Modal */}
      <QuestionModal
        isOpen={isQuestionModalOpen}
        onClose={() => setIsQuestionModalOpen(false)}
        onSubmit={handleSaveQuestion}
        question={selectedQuestion}
      />

      {/* Confirm Deactivate Dialog */}
      <ConfirmDialog
        isOpen={!!deletingQuestion}
        onClose={() => setDeletingQuestion(null)}
        onConfirm={handleDeactivateConfirm}
        title="تعطيل السؤال"
        message={`هل أنت متأكد من تعطيل السؤال "${deletingQuestion?.text_ar}"؟ لن يتم إرساله للسائقين الجدد ولكن ستظل الإجابات السابقة محفوظة.`}
        confirmText="نعم، قم بالتعطيل"
        variant="danger"
      />
    </div>
  );
};

export default SettingsPage;
