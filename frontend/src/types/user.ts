export interface User {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at?: string;
}

export interface CreateUserData {
  name: string;
  email: string;
  password: string;
  is_active?: boolean;
}

export interface UpdateUserData {
  name: string;
  email: string;
  password?: string;
  is_active?: boolean;
}

export interface UserFilterParams {
  search?: string;
  is_active?: 'all' | 'true' | 'false' | boolean;
  per_page?: number;
  page?: number;
  sort_by?: 'id' | 'name' | 'email' | 'is_active' | 'last_login_at' | 'created_at';
  sort_direction?: 'asc' | 'desc';
}
