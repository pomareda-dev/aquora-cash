export interface ContributionData {
  id: number;
  date: string;
  amount: number;
  notes: string | null;
  is_sandbox?: boolean;
}

export interface GoalData {
  id: number;
  name: string;
  target_amount: number;
  target_date: string | null;
  progress_amount: number;
  percent: number;
  remaining_amount: number;
  days_to_target: number | null;
  can_delete: boolean;
  is_complete: boolean;
  contributions: ContributionData[];
  simulated_amount?: number;
}

export interface GoalsSummary {
  apartado: number;
  available_real: number;
  apartado_simulado?: number;
}
