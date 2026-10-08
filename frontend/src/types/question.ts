export type QuestionType = 'single_choice' | 'yes_no' | 'number' | 'text';

export interface QuestionOption {
  id?: number;
  value: string;
  label_ar: string;
  sort_order?: number;
  is_active?: boolean;
}

export interface QuestionVersion {
  id: number;
  version: number;
  text_ar: string;
  type: QuestionType;
  options_snapshot: QuestionOption[] | null;
  answers_count?: number;
  created_at: string;
}

export interface Question {
  id: number;
  key: string;
  type: QuestionType;
  type_label: string;
  text_ar: string;
  version: number;
  sort_order: number;
  is_active: boolean;
  is_required: boolean;
  is_filter: boolean;
  is_system?: boolean;
  options: QuestionOption[];
  options_count: number;
  answers_count: number;
  versions_count: number;
  versions?: QuestionVersion[];
  created_at: string;
  updated_at: string;
}

export interface CreateQuestionPayload {
  key: string;
  type: QuestionType;
  text_ar: string;
  is_required: boolean;
  is_filter: boolean;
  is_active: boolean;
  sort_order?: number;
  options?: Array<{
    value: string;
    label_ar: string;
    sort_order?: number;
  }>;
}

export interface UpdateQuestionPayload {
  key: string;
  type: QuestionType;
  text_ar: string;
  is_required: boolean;
  is_filter: boolean;
  is_active: boolean;
  sort_order?: number;
  options?: Array<{
    value: string;
    label_ar: string;
    sort_order?: number;
  }>;
}

export interface DriverAnswerItem {
  id: number;
  question_id: number;
  question_key: string;
  question_text: string;
  version: number;
  type: QuestionType;
  type_label: string;
  display_value: string;
  value_text: string | null;
  value_number: number | null;
  option_id: number | null;
  option_label: string | null;
  is_skipped: boolean;
  answered_at: string;
  available_options?: Array<{
    id: number;
    value: string;
    label_ar: string;
  }>;
}

export interface DriverAnswersResponse {
  driver_id: number;
  driver_status: string;
  driver_status_label: string;
  needs_review: boolean;
  review_reason: string | null;
  next_question: {
    id: number;
    key: string;
    text_ar: string;
    type: QuestionType;
  } | null;
  answers: DriverAnswerItem[];
  history: Array<{
    id: number;
    question_id: number;
    question_key: string;
    question_text: string;
    version: number;
    display_value: string;
    is_skipped: boolean;
    answered_at: string;
  }>;
}
