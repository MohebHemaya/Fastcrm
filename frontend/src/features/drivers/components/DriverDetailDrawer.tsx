import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Textarea,
  Divider,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  UserCircleIcon,
  PhoneIcon,
  CalendarIcon,
  UsersIcon,
  ClockIcon,
  ArrowsUpDownIcon,
  CheckIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
  QuestionMarkCircleIcon,
  ArrowPathIcon,
  PaperAirplaneIcon,
  PencilSquareIcon,
  ExclamationCircleIcon,
  SparklesIcon,
} from '@/components/icons';
import { ar } from '@/i18n/ar';
import { useUiStore } from '@/lib/stores/useUiStore';
import driverService from '@/services/driverService';
import type { Driver } from '@/types/driver';
import type { DriverAnswersResponse, DriverAnswerItem } from '@/types/question';
import DriverStatusBadge from './DriverStatusBadge';
import ChangeStatusModal from './ChangeStatusModal';
import EditAnswerModal from './EditAnswerModal';
import { useIsMobile } from '@/hooks/useMediaQuery';

interface DriverDetailDrawerProps {
  driverId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onDriverUpdated?: (driver: Driver) => void;
}

export const DriverDetailDrawer: React.FC<DriverDetailDrawerProps> = ({
  driverId,
  isOpen,
  onClose,
  onDriverUpdated,
}) => {
  const isMobile = useIsMobile();
  const [driver, setDriver] = useState<Driver | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isSavingNotes, setIsSavingNotes] = useState(false);
  const [name, setName] = useState('');
  const [notes, setNotes] = useState('');
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);

  // Questionnaire state
  const [answersData, setAnswersData] = useState<DriverAnswersResponse | null>(null);
  const [selectedAnswerToEdit, setSelectedAnswerToEdit] = useState<DriverAnswerItem | null>(null);
  const [isResendingQuestion, setIsResendingQuestion] = useState(false);
  const [isRequestingUpdate, setIsRequestingUpdate] = useState(false);

  const addToast = useUiStore((state) => state.addToast);

  const fetchDriverDetails = async (id: number) => {
    setIsLoading(true);
    try {
      const [driverRes, answersRes] = await Promise.all([
        driverService.getDriver(id),
        driverService.getDriverAnswers(id).catch(() => null),
      ]);
      setDriver(driverRes.data);
      setName(driverRes.data.name || '');
      setNotes(driverRes.data.notes || '');
      setAnswersData(answersRes);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
      onClose();
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendQuestion = async () => {
    if (!driver) return;
    setIsResendingQuestion(true);
    try {
      await driverService.resendQuestion(driver.id);
      addToast({
        title: 'تم إعادة إرسال السؤال',
        message: 'تم إعادة إرسال السؤال الحالي للسائق عبر واتساب بنجاح.',
        type: 'success',
      });
      fetchDriverDetails(driver.id);
    } catch {
      addToast({
        title: 'خطأ',
        message: 'تعذر إعادة إرسال السؤال.',
        type: 'error',
      });
    } finally {
      setIsResendingQuestion(false);
    }
  };

  const handleRequestAnswerUpdate = async () => {
    if (!driver) return;
    setIsRequestingUpdate(true);
    try {
      await driverService.requestAnswerUpdate(driver.id);
      addToast({
        title: 'تم إرسال طلب التحديث',
        message: 'تم إرسال طلب تحديث الإجابات للأسئلة الجديدة للسائق.',
        type: 'success',
      });
      fetchDriverDetails(driver.id);
    } catch (err: any) {
      addToast({
        title: 'تنبيه',
        message: err.response?.data?.message || 'تعذر طلب تحديث الإجابات.',
        type: 'error',
      });
    } finally {
      setIsRequestingUpdate(false);
    }
  };

  const handleSaveAnswerEdit = async (payload: any) => {
    if (!driver || !selectedAnswerToEdit) return;
    try {
      await driverService.updateDriverAnswer(driver.id, selectedAnswerToEdit.id, payload);
      addToast({
        title: 'تم حفظ التعديل',
        message: 'تم تعديل الإجابة وتوثيقها في سجل النشاطات بنجاح.',
        type: 'success',
      });
      fetchDriverDetails(driver.id);
    } catch {
      addToast({
        title: 'خطأ',
        message: 'تعذر تعديل الإجابة.',
        type: 'error',
      });
    }
  };

  useEffect(() => {
    if (isOpen && driverId) {
      fetchDriverDetails(driverId);
    } else {
      setDriver(null);
    }
  }, [isOpen, driverId]);

  const handleSaveInfo = async () => {
    if (!driver) return;
    setIsSavingNotes(true);
    try {
      const response = await driverService.updateDriver(driver.id, {
        name: name.trim() || undefined,
        notes: notes.trim() || undefined,
      });
      setDriver(response.data);
      addToast({
        title: ar.drivers.driverUpdated,
        message: ar.drivers.driverUpdated,
        type: 'success',
      });
      onDriverUpdated?.(response.data);
    } catch {
      addToast({
        title: ar.errors.generalError,
        message: ar.errors.generalError,
        type: 'error',
      });
    } finally {
      setIsSavingNotes(false);
    }
  };

  const handleStatusChangeSuccess = (updatedDriver: Driver) => {
    setDriver(updatedDriver);
    onDriverUpdated?.(updatedDriver);
  };

  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        placement="center"
        size={isMobile ? 'full' : '2xl'}
        scrollBehavior="inside"
        classNames={{
          base: isMobile
            ? 'h-[100dvh] max-h-[100dvh] m-0 rounded-none bg-content1 text-foreground rtl'
            : 'border border-default-200 bg-content1 text-foreground rtl max-h-[90vh]',
          header: 'border-b border-default-100 pb-3',
          footer: 'border-t border-default-100 pt-3',
        }}
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {isLoading || !driver ? (
                  <div className="flex items-center gap-2">
                    <Spinner size="sm" />
                    <span>{ar.app.loading}</span>
                  </div>
                ) : (
                  <div className="flex flex-wrap items-center justify-between gap-3 w-full pe-6">
                    <div className="flex items-center gap-3">
                      <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-100 text-primary">
                        <UserCircleIcon className="h-6 w-6" />
                      </div>
                      <div>
                        <h2 className="text-lg font-bold">{driver.name || ar.drivers.unnamed}</h2>
                        <p className="text-xs font-mono text-default-500" dir="ltr">
                          {driver.phone}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <DriverStatusBadge status={driver.status.value} label={driver.status.label} size="md" />
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        startContent={<ArrowsUpDownIcon className="h-4 w-4" />}
                        onPress={() => setIsStatusModalOpen(true)}
                      >
                        {ar.drivers.changeStatus}
                      </Button>
                    </div>
                  </div>
                )}
              </ModalHeader>

              <ModalBody className="py-4 flex flex-col gap-5">
                {isLoading ? (
                  <div className="flex h-64 items-center justify-center">
                    <Spinner label={ar.app.loading} />
                  </div>
                ) : driver ? (
                  <>
                    {/* Alerts for special terminal statuses */}
                    {driver.status.value === 'opted_out' && (
                      <div className="flex items-center gap-2 rounded-lg bg-danger-50 p-3 text-xs text-danger border border-danger-200">
                        <ExclamationTriangleIcon className="h-4 w-4 shrink-0" />
                        <span>{ar.drivers.optedOutAlert}</span>
                      </div>
                    )}
                    {driver.status.value === 'blocked' && (
                      <div className="flex items-center gap-2 rounded-lg bg-danger-50 p-3 text-xs text-danger border border-danger-200">
                        <ExclamationTriangleIcon className="h-4 w-4 shrink-0" />
                        <span>{ar.drivers.blockedAlert}</span>
                      </div>
                    )}

                    {/* Basic Meta Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                      <div className="flex items-center gap-2.5 rounded-lg border border-default-200 bg-default-50/50 p-3">
                        <PhoneIcon className="h-4 w-4 text-default-400 shrink-0" />
                        <div>
                          <span className="block text-xs text-default-400">{ar.drivers.phone}</span>
                          <span className="font-mono font-medium" dir="ltr">
                            {driver.phone}
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center gap-2.5 rounded-lg border border-default-200 bg-default-50/50 p-3">
                        <CalendarIcon className="h-4 w-4 text-default-400 shrink-0" />
                        <div>
                          <span className="block text-xs text-default-400">{ar.drivers.joinedAt}</span>
                          <span className="font-medium text-xs">
                            {new Date(driver.created_at).toLocaleDateString('ar-EG', {
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric',
                            })}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Driver Metrics Card (Phase 6) */}
                    <div className="flex flex-col gap-2 rounded-xl border border-default-200 bg-default-50/50 p-4">
                      <div className="flex items-center justify-between">
                        <h3 className="text-sm font-bold flex items-center gap-1.5 text-default-700">
                          <SparklesIcon className="h-4 w-4 text-amber-500" />
                          <span>{ar.driverMetrics.title}</span>
                        </h3>
                        {driver.metrics?.avg_rating ? (
                          <span className="text-xs font-bold text-amber-500 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                            ⭐ {driver.metrics.avg_rating} ({driver.metrics.ratings_count || 1} تقييم)
                          </span>
                        ) : (
                          <span className="text-xs text-default-400">{ar.driverMetrics.noMetrics}</span>
                        )}
                      </div>

                      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-center">
                        <div className="bg-background p-2 rounded-lg border border-default-100">
                          <span className="text-[11px] text-default-400 block">{ar.driverMetrics.tripsCompleted}</span>
                          <span className="font-bold text-foreground text-sm">
                            {driver.metrics?.trips_completed ?? 0}
                          </span>
                        </div>
                        <div className="bg-background p-2 rounded-lg border border-default-100">
                          <span className="text-[11px] text-default-400 block">{ar.driverMetrics.noShows}</span>
                          <span className={`font-bold text-sm ${driver.metrics?.no_shows ? 'text-danger' : 'text-foreground'}`}>
                            {driver.metrics?.no_shows ?? 0}
                          </span>
                        </div>
                        <div className="bg-background p-2 rounded-lg border border-default-100">
                          <span className="text-[11px] text-default-400 block">{ar.driverMetrics.lateCount}</span>
                          <span className="font-bold text-foreground text-sm">
                            {driver.metrics?.late_count ?? 0}
                          </span>
                        </div>
                        <div className="bg-background p-2 rounded-lg border border-default-100">
                          <span className="text-[11px] text-default-400 block">{ar.driverMetrics.cancellations}</span>
                          <span className="font-bold text-foreground text-sm">
                            {driver.metrics?.cancellations ?? 0}
                          </span>
                        </div>
                      </div>

                      {driver.metrics?.last_trip_at && (
                        <p className="text-[11px] text-default-400 text-start pt-1">
                          {ar.driverMetrics.lastTrip}: {new Date(driver.metrics.last_trip_at).toLocaleDateString('ar-EG')}
                        </p>
                      )}
                    </div>

                    {/* Edit Name & Notes */}
                    <div className="flex flex-col gap-3 rounded-xl border border-default-200 bg-default-50/30 p-4">
                      <div className="flex items-center justify-between">
                        <h3 className="text-sm font-bold flex items-center gap-1.5 text-default-700">
                          <ShieldCheckIcon className="h-4 w-4 text-primary" />
                          <span>{ar.drivers.editDriver}</span>
                        </h3>
                        <Button
                          size="sm"
                          color="primary"
                          variant="solid"
                          isLoading={isSavingNotes}
                          onPress={handleSaveInfo}
                          startContent={!isSavingNotes && <CheckIcon className="h-3.5 w-3.5" />}
                        >
                          {ar.common.save}
                        </Button>
                      </div>

                      <Input
                        label={ar.drivers.name}
                        placeholder={ar.drivers.unnamed}
                        value={name}
                        onValueChange={setName}
                        size="sm"
                        variant="bordered"
                        labelPlacement="outside"
                      />

                      <Textarea
                        label={ar.drivers.notes}
                        placeholder={ar.drivers.notesPlaceholder}
                        value={notes}
                        onValueChange={setNotes}
                        minRows={2}
                        size="sm"
                        variant="bordered"
                        labelPlacement="outside"
                      />
                    </div>

                    {/* WhatsApp Groups */}
                    <div className="flex flex-col gap-2">
                      <h3 className="text-sm font-bold flex items-center gap-1.5 text-default-700">
                        <UsersIcon className="h-4 w-4 text-primary" />
                        <span>{ar.drivers.groups} ({driver.groups?.length || 0})</span>
                      </h3>
                      {driver.groups && driver.groups.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                          {driver.groups.map((group) => (
                            <Chip
                              key={group.id}
                              variant="flat"
                              color="default"
                              size="sm"
                              className="bg-default-100"
                            >
                              <span className="font-medium">{group.name}</span>
                              {group.first_seen_at && (
                                <span className="ms-1 text-[10px] text-default-400">
                                  ({new Date(group.first_seen_at).toLocaleDateString('ar-EG')})
                                </span>
                              )}
                            </Chip>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-default-400">لا توجد مجموعات مرتبطة بهذا السائق حالياً.</p>
                      )}
                    </div>

                    <Divider />

                    {/* Questionnaire Answers */}
                    <div className="flex flex-col gap-3">
                      <div className="flex items-center justify-between flex-wrap gap-2">
                        <h3 className="text-sm font-bold flex items-center gap-1.5 text-default-700">
                          <QuestionMarkCircleIcon className="h-4 w-4 text-primary" />
                          <span>إجابات الاستبيان ({answersData?.answers?.length || 0})</span>
                        </h3>

                        <div className="flex items-center gap-1.5">
                          {answersData?.next_question && (
                            <Button
                              size="sm"
                              variant="flat"
                              color="primary"
                              isLoading={isResendingQuestion}
                              startContent={!isResendingQuestion && <PaperAirplaneIcon className="w-3.5 h-3.5 rtl:-scale-x-100" />}
                              onPress={handleResendQuestion}
                            >
                              إعادة إرسال السؤال الحالي
                            </Button>
                          )}

                          {driver.status.value === 'active' && (
                            <Button
                              size="sm"
                              variant="flat"
                              color="secondary"
                              isLoading={isRequestingUpdate}
                              startContent={!isRequestingUpdate && <ArrowPathIcon className="w-3.5 h-3.5" />}
                              onPress={handleRequestAnswerUpdate}
                            >
                              طلب تحديث الإجابات
                            </Button>
                          )}
                        </div>
                      </div>

                      {answersData?.needs_review && (
                        <div className="p-3 bg-danger-50 dark:bg-danger-950/30 border border-danger-200 dark:border-danger-800 rounded-xl text-xs text-danger flex items-start gap-2">
                          <ExclamationCircleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                          <div>
                            <p className="font-bold">السائق يحتاج إلى مراجعة بياناته</p>
                            <p className="text-[11px] mt-0.5">{answersData.review_reason || 'تعذر استكمال بعض الأسئلة آلياً عبر واتساب.'}</p>
                          </div>
                        </div>
                      )}

                      {answersData?.answers && answersData.answers.length > 0 ? (
                        <div className="space-y-2">
                          {answersData.answers.map((ans) => (
                            <div
                              key={ans.id}
                              className="p-3 rounded-xl border border-divider bg-content2/30 flex items-start justify-between gap-3"
                            >
                              <div className="space-y-1 text-start">
                                <div className="flex items-center gap-1.5 flex-wrap">
                                  <span className="text-xs font-semibold text-foreground">
                                    {ans.question_text}
                                  </span>
                                  <Chip size="sm" variant="flat" color="default" className="text-[10px] h-5">
                                    v{ans.version}
                                  </Chip>
                                  {ans.is_skipped && (
                                    <Chip size="sm" variant="flat" color="danger" className="text-[10px] h-5">
                                      تم التخطي
                                    </Chip>
                                  )}
                                </div>
                                <p className="text-sm font-bold text-primary">
                                  {ans.display_value}
                                </p>
                                <span className="text-[11px] text-default-400 block">
                                  تمت الإجابة: {new Date(ans.answered_at).toLocaleString('ar-EG')}
                                </span>
                              </div>

                              <Button
                                isIconOnly
                                size="sm"
                                variant="light"
                                color="primary"
                                onPress={() => setSelectedAnswerToEdit(ans)}
                              >
                                <PencilSquareIcon className="w-3.5 h-3.5" />
                              </Button>
                            </div>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-default-400">لم يتم تسجيل أي إجابات استبيان لهذا السائق حتى الآن.</p>
                      )}
                    </div>

                    <Divider />

                    {/* Status History Timeline */}
                    <div className="flex flex-col gap-3">
                      <h3 className="text-sm font-bold flex items-center gap-1.5 text-default-700">
                        <ClockIcon className="h-4 w-4 text-primary" />
                        <span>{ar.drivers.statusHistory}</span>
                      </h3>

                      {driver.status_histories && driver.status_histories.length > 0 ? (
                        <div className="relative flex flex-col gap-3 ps-4 before:absolute before:inset-y-0 before:start-1.5 before:w-0.5 before:bg-default-200">
                          {driver.status_histories.map((hist) => (
                            <div key={hist.id} className="relative flex flex-col gap-1 text-xs">
                              <span className="absolute -start-4 top-1.5 h-2 w-2 rounded-full bg-primary" />
                              <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1 font-medium">
                                  {hist.from_status ? (
                                    <>
                                      <span className="text-default-500">{hist.from_status_label || hist.from_status}</span>
                                      <span className="text-default-400">←</span>
                                    </>
                                  ) : null}
                                  <DriverStatusBadge status={hist.to_status} label={hist.to_status_label} size="sm" />
                                </div>
                                <span className="text-default-400 text-[11px]">
                                  {new Date(hist.created_at).toLocaleString('ar-EG')}
                                </span>
                                {hist.changed_by && (
                                  <span className="text-default-500 text-[11px]">
                                    ({ar.drivers.changedBy}: {hist.changed_by.name})
                                  </span>
                                )}
                              </div>
                              {hist.reason && (
                                <p className="text-default-600 bg-default-100/70 p-2 rounded-md mt-1">
                                  {hist.reason}
                                </p>
                              )}
                            </div>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-default-400">{ar.drivers.noHistory}</p>
                      )}
                    </div>
                  </>
                ) : null}
              </ModalBody>

              <ModalFooter className="safe-bottom">
                <Button variant="flat" color="default" className="w-full sm:w-auto min-h-[44px]" onPress={onClose}>
                  {ar.common.close}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Change Status Modal */}
      <ChangeStatusModal
        driver={driver}
        isOpen={isStatusModalOpen}
        onClose={() => setIsStatusModalOpen(false)}
        onSuccess={handleStatusChangeSuccess}
      />

      {/* Edit Answer Modal */}
      <EditAnswerModal
        isOpen={!!selectedAnswerToEdit}
        onClose={() => setSelectedAnswerToEdit(null)}
        answer={selectedAnswerToEdit}
        onSave={handleSaveAnswerEdit}
      />
    </>
  );
};

export default DriverDetailDrawer;
