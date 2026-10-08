import { describe, it, expect } from 'vitest';
import { CARGO_TYPES, getFilterSuggestionsForCargoType } from '@/data/cargoTypes';
import type { Question } from '@/types/question';

describe('Cargo Types & Smart Filter Suggestions', () => {
  const mockQuestions: Question[] = [
    {
      id: 1,
      key: 'vehicle_type',
      type: 'single_choice',
      type_label: 'اختيار من متعدد',
      text_ar: 'ما هو نوع سيارتك؟',
      version: 1,
      sort_order: 1,
      is_active: true,
      is_required: true,
      is_filter: true,
      options: [
        { value: 'pickup', label_ar: 'بيك أب' },
        { value: 'jumbo', label_ar: 'جامبو' },
        { value: 'van', label_ar: 'فان مغلق' },
        { value: 'truck', label_ar: 'تريلا' },
      ],
      options_count: 4,
      answers_count: 10,
      versions_count: 1,
      created_at: '2026-10-01',
      updated_at: '2026-10-01',
    },
    {
      id: 2,
      key: 'has_refrigeration',
      type: 'yes_no',
      type_label: 'نعم / لا',
      text_ar: 'هل السيارة مجهزة بصندوق تبريد / تجميد؟',
      version: 1,
      sort_order: 2,
      is_active: true,
      is_required: true,
      is_filter: true,
      options: [],
      options_count: 0,
      answers_count: 10,
      versions_count: 1,
      created_at: '2026-10-01',
      updated_at: '2026-10-01',
    },
  ];

  it('lists common cargo types with presets', () => {
    expect(CARGO_TYPES.length).toBeGreaterThan(5);
    expect(CARGO_TYPES.some((c) => c.key === 'مواد غذائية مبردة')).toBe(true);
    expect(CARGO_TYPES.some((c) => c.key === 'بضائع عامة وجافة')).toBe(true);
    expect(CARGO_TYPES.some((c) => c.key === 'أخرى')).toBe(true);
  });

  it('automatically suggests refrigeration filter for refrigerated and frozen food', () => {
    const refrigeratedSuggestions = getFilterSuggestionsForCargoType(
      'مواد غذائية مبردة',
      mockQuestions
    );
    expect(refrigeratedSuggestions.length).toBe(1);
    expect(refrigeratedSuggestions[0].question_id).toBe(2);
    expect(refrigeratedSuggestions[0].question_key).toBe('has_refrigeration');
    expect(refrigeratedSuggestions[0].value).toBe('yes');

    const frozenSuggestions = getFilterSuggestionsForCargoType(
      'مجمدات ولحوم وأسماك',
      mockQuestions
    );
    expect(frozenSuggestions.length).toBe(1);
    expect(frozenSuggestions[0].question_key).toBe('has_refrigeration');
  });

  it('does not suggest refrigeration filter for dry or general goods', () => {
    const drySuggestions = getFilterSuggestionsForCargoType(
      'بضائع عامة وجافة',
      mockQuestions
    );
    expect(drySuggestions.length).toBe(0);
  });
});
