export type Role = 'employee' | 'hr' | 'helpdesk';
export interface CurrentUser { id: number; name: string; role: Role; company_id: number | null; }
export interface Company { id: number; name: string; }
export interface Employee { id: number; name: string; email: string; company_id: number; company?: Company; }
export interface Medication { id: number; name: string; dosage: string | null; instructions: string | null; covered_amount_cents: number; }
export interface Visit { id: number; clinic: string; visited_on: string; covered_amount_cents: number; status: 'pending' | 'approved' | 'rejected'; visited_time: string | null; doctor_name: string | null; doctor_title: string | null; diagnosis: string | null; sick_leave_started_on: string | null; sick_leave_ended_on: string | null; medications: Medication[]; user?: Employee | null; }
export interface Benefits { year: number; currency: string; plan_name: string; annual_limit: number; used_amount: number; remaining_balance: number; }
export interface BenefitPlan { id: number; name: string; company_id: number; annual_limit_cents: number; }

// Optional medical fields use null when the employee leaves them blank.
export interface VisitSubmission { clinic: string; visited_on: string; covered_amount: number; visited_time: string | null; doctor_name: string | null; doctor_title: string | null; diagnosis: string | null; sick_leave_started_on: string | null; sick_leave_ended_on: string | null; }
