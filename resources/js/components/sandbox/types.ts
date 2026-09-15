export interface SandboxMovementData {
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
