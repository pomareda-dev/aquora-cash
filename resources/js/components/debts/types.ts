export interface DebtData {
  id: number;
  name: string;
  principal_amount: number;
  disbursement_date: string;
  installment_amount: number;
  installments_count: number;
  payment_dates: string[];
  closed_at: string | null;
  rate_factor: number;
  total_to_pay: number;
  paid_installments: number;
  remaining: number;
  can_delete: boolean;
  is_active: boolean;
  is_sandbox?: boolean;
}

export interface DebtDetailData {
  id: number;
  name: string;
  principal_amount: number;
  disbursement_date: string;
  installment_amount: number;
  installments_count: number;
  payment_dates: string[];
  closed_at: string | null;
  rate_factor: number;
  total_to_pay: number;
  paid: number;
  paid_installments: number;
  remaining: number;
  is_active: boolean;
}

export interface DebtMovementData {
  id: number;
  date: string;
  description: string;
  category_id: number | null;
  category_name: string | null;
  category_color: string | null;
  amount: number;
  is_projected: boolean;
  notes: string | null;
}

export interface DebtStrategyItem {
  id: number;
  name: string;
  remaining: number;
  factor: number;
  installment: number;
  is_sandbox?: boolean;
}

export interface DebtStrategyData {
  avalanche: DebtStrategyItem[];
  snowball: DebtStrategyItem[];
  weighted_factor: number;
}
