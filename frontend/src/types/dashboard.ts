export interface DashboardSummary {
  drivers_by_status: Record<string, number>;
  total_drivers: number;
  active_drivers_count: number;
  opted_out_drivers_count: number;
  orders_this_month: {
    total: number;
    by_status: Record<string, number>;
  };
  offer_acceptance_rate: number;
  avg_time_to_fill_minutes: number | null;
  monthly_trends: Array<{
    month: string;
    label: string;
    total_orders: number;
    completed_orders: number;
  }>;
  date_range: {
    start_date: string | null;
    end_date: string | null;
  };
}

export interface GroupPerformance {
  group_id: number;
  group_name: string;
  imported: number;
  invited: number;
  interested: number;
  active: number;
  declined: number;
  opted_out: number;
  conversion_rate: number;
}

export interface DashboardFilterParams {
  start_date?: string;
  end_date?: string;
}
