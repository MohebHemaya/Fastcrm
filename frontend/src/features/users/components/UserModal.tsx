import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Switch,
} from '@heroui/react';
import {
  UserIcon,
  EnvelopeIcon,
  LockClosedIcon,
  EyeIcon,
  EyeSlashIcon,
} from '@heroicons/react/24/outline';
import type { User, CreateUserData, UpdateUserData } from '@/types/user';
import { ar } from '@/i18n/ar';

interface UserModalProps {
  isOpen: boolean;
  onClose: () => void;
  user: User | null;
  currentUserId: number | null;
  onSubmit: (data: CreateUserData | UpdateUserData) => Promise<void>;
}

export const UserModal: React.FC<UserModalProps> = ({
  isOpen,
  onClose,
  user,
  currentUserId,
  onSubmit,
}) => {
  const isEditing = Boolean(user);
  const isSelf = isEditing && user?.id === currentUserId;

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      if (user) {
        setName(user.name);
        setEmail(user.email);
        setPassword('');
        setIsActive(user.is_active);
      } else {
        setName('');
        setEmail('');
        setPassword('');
        setIsActive(true);
      }
      setShowPassword(false);
      setErrors({});
    }
  }, [isOpen, user]);

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (!name.trim()) {
      errs.name = ar.auth.emailRequired ? 'اسم المستخدم مطلوب.' : 'مطلوب';
    }

    if (!email.trim()) {
      errs.email = ar.auth.emailRequired;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errs.email = ar.auth.emailInvalid;
    }

    if (!isEditing) {
      if (!password) {
        errs.password = ar.auth.passwordRequired;
      } else if (password.length < 8) {
        errs.password = ar.auth.passwordMinLength;
      }
    } else if (password && password.length < 8) {
      errs.password = ar.auth.passwordMinLength;
    }

    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e?: React.FormEvent) => {
    if (e?.preventDefault) {
      e.preventDefault();
    }
    if (!validate()) return;

    setIsSubmitting(true);
    try {
      if (isEditing) {
        const payload: UpdateUserData = {
          name: name.trim(),
          email: email.trim(),
          is_active: isActive,
        };
        if (password) {
          payload.password = password;
        }
        await onSubmit(payload);
      } else {
        const payload: CreateUserData = {
          name: name.trim(),
          email: email.trim(),
          password,
          is_active: isActive,
        };
        await onSubmit(payload);
      }
      onClose();
    } catch (err: any) {
      if (err?.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        for (const [key, msgs] of Object.entries(err.response.data.errors)) {
          backendErrors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
        }
        setErrors(backendErrors);
      } else if (err?.response?.data?.message) {
        setErrors({ general: err.response.data.message });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="center"
      size="md"
      classNames={{
        base: 'bg-content1 border border-default-200 shadow-xl',
        header: 'border-b border-default-100',
        footer: 'border-t border-default-100',
      }}
    >
      <ModalContent>
        {() => (
          <form onSubmit={handleSubmit}>
            <ModalHeader className="flex items-center gap-2">
              <div className="p-2 rounded-xl bg-primary-50 text-primary dark:bg-primary-950/40">
                <UserIcon className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-base font-bold text-default-900">
                  {isEditing ? ar.users.editUser : ar.users.addUser}
                </h3>
                <p className="text-xs text-default-500 font-normal">
                  {isEditing ? user?.email : 'إدخال بيانات المستخدم وتعيين الصلاحيات'}
                </p>
              </div>
            </ModalHeader>

            <ModalBody className="py-4 space-y-4">
              {errors.general && (
                <div className="p-3 text-xs bg-danger-50 border border-danger-200 text-danger-700 rounded-xl">
                  {errors.general}
                </div>
              )}

              {/* Name */}
              <Input
                label={ar.users.name}
                placeholder={ar.users.namePlaceholder}
                value={name}
                onValueChange={setName}
                variant="bordered"
                size="sm"
                isRequired
                isInvalid={Boolean(errors.name)}
                errorMessage={errors.name}
                startContent={<UserIcon className="w-4 h-4 text-default-400" />}
              />

              {/* Email */}
              <Input
                type="email"
                label={ar.users.email}
                placeholder={ar.users.emailPlaceholder}
                value={email}
                onValueChange={setEmail}
                variant="bordered"
                size="sm"
                isRequired
                isInvalid={Boolean(errors.email)}
                errorMessage={errors.email}
                startContent={<EnvelopeIcon className="w-4 h-4 text-default-400" />}
                dir="ltr"
                className="text-start"
              />

              {/* Password */}
              <Input
                type={showPassword ? 'text' : 'password'}
                label={ar.users.password}
                placeholder={isEditing ? ar.users.passwordOptionalPlaceholder : ar.users.passwordPlaceholder}
                value={password}
                onValueChange={setPassword}
                variant="bordered"
                size="sm"
                isRequired={!isEditing}
                isInvalid={Boolean(errors.password)}
                errorMessage={errors.password}
                startContent={<LockClosedIcon className="w-4 h-4 text-default-400" />}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="focus:outline-none text-default-400 hover:text-default-600"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeSlashIcon className="w-4 h-4" />
                    ) : (
                      <EyeIcon className="w-4 h-4" />
                    )}
                  </button>
                }
              />

              {/* Active Toggle */}
              <div className="flex items-center justify-between p-3 rounded-xl border border-default-200 bg-default-50/50">
                <div className="space-y-0.5">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-default-800">
                      {ar.users.status}:
                    </span>
                    <span
                      className={`text-xs font-semibold ${
                        isActive
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : 'text-default-500'
                      }`}
                    >
                      {isActive ? ar.users.active : ar.users.inactive}
                    </span>
                  </div>
                  <p className="text-[11px] text-default-400">
                    {isSelf
                      ? ar.users.cannotDeactivateSelf
                      : isActive
                      ? 'يمكن للمستخدم تسجيل الدخول وإدارة النظام'
                      : 'تم حظر المستخدم من تسجيل الدخول'}
                  </p>
                </div>
                <Switch
                  size="sm"
                  color="success"
                  isSelected={isActive}
                  onValueChange={setIsActive}
                  isDisabled={isSelf}
                  aria-label={ar.users.status}
                />
              </div>
            </ModalBody>

            <ModalFooter>
              <Button
                variant="flat"
                color="default"
                size="sm"
                onPress={onClose}
                isDisabled={isSubmitting}
              >
                {ar.common.cancel}
              </Button>
              <Button
                type="submit"
                color="primary"
                size="sm"
                isLoading={isSubmitting}
                className="font-bold"
                onPress={() => handleSubmit()}
              >
                {isEditing ? ar.common.save : ar.common.add}
              </Button>
            </ModalFooter>
          </form>
        )}
      </ModalContent>
    </Modal>
  );
};

export default UserModal;
