export type ImportStatusValue =
  | 'pending'
  | 'parsing'
  | 'ready_for_review'
  | 'committing'
  | 'completed'
  | 'failed'
  | 'cancelled';

export type ImportRowClassification =
  | 'new'
  | 'duplicate_in_file'
  | 'existing'
  | 'active'
  | 'invalid';

export interface ImportCounts {
  total: number;
  new: number;
  duplicate_in_file: number;
  existing: number;
  active: number;
  invalid: number;
}

export interface ImportItem {
  id: number;
  original_name: string;
  status: ImportStatusValue;
  status_label: string;
  user?: {
    id: number;
    name: string;
  } | null;
  counts: ImportCounts;
  processed_chunks: number;
  total_chunks: number;
  error_message?: string | null;
  created_at: string;
  updated_at: string;
}

export interface ImportRow {
  id: number;
  row_number: number;
  raw_phone: string;
  normalized_phone: string | null;
  group_name: string | null;
  classification: ImportRowClassification;
  classification_label: string;
  error: string | null;
  driver_id: number | null;
}

export interface ImportRowsFilterParams {
  classification?: string;
  search?: string;
  page?: number;
  per_page?: number;
}
