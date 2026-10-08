import React, { useState, useEffect, useRef } from 'react';
import { Input, type InputProps } from '@heroui/react';
import { MagnifyingGlassIcon } from '@/components/icons';

export interface SearchInputProps
  extends Omit<InputProps, 'value' | 'defaultValue' | 'onChange' | 'onValueChange'> {
  value?: string;
  onSearch: (query: string) => void;
  debounceMs?: number;
}

export const SearchInput: React.FC<SearchInputProps> = ({
  value = '',
  onSearch,
  debounceMs = 300,
  placeholder = 'بحث...',
  variant = 'bordered',
  size = 'sm',
  isClearable = true,
  className = 'w-full sm:w-64',
  ...props
}) => {
  const [internalValue, setInternalValue] = useState<string>(value);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    setInternalValue(value);
  }, [value]);

  const handleChange = (newVal: string) => {
    setInternalValue(newVal);

    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(() => {
      onSearch(newVal);
    }, debounceMs);
  };

  const handleClear = () => {
    setInternalValue('');
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }
    onSearch('');
  };

  useEffect(() => {
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, []);

  return (
    <Input
      {...props}
      aria-label={props['aria-label'] || placeholder || 'بحث'}
      size={size}
      variant={variant}
      placeholder={placeholder}
      value={internalValue}
      onValueChange={handleChange}
      onClear={handleClear}
      isClearable={isClearable}
      className={className}
      classNames={{
        ...props.classNames,
        base: `!mt-0 ${props.classNames?.base || ''}`,
        inputWrapper: `h-9 min-h-9 border-1.5 border-default-300 dark:border-default-200 bg-background shadow-xs hover:border-default-400 group-data-[focus=true]:border-primary ${props.classNames?.inputWrapper || ''}`,
        input: `!outline-none !border-none text-small placeholder:text-default-400 ${props.classNames?.input || ''}`,
      }}
      startContent={<MagnifyingGlassIcon className="h-4 w-4 text-default-400 shrink-0" />}
    />
  );
};

export default SearchInput;
