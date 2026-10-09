import { ApiError, fetchWithAuth } from './auth';
import type { Benefits, Visit, Employee, BenefitPlan } from '../../types';

export async function getBenefits(signal?: AbortSignal): Promise<Benefits | null> {
  try { return await fetchWithAuth<Benefits>('/benefits', { signal }); }
  catch (error) { if (error instanceof ApiError && error.status === 404) return null; throw error; }
}
export const getVisits = (signal?: AbortSignal) => fetchWithAuth<{ visits: Visit[] }>('/visits', { signal });
export const getEmployees = (signal?: AbortSignal) => fetchWithAuth<{ employees: Employee[] }>('/hr/employees', { signal });
export const getBenefitPlans = (signal?: AbortSignal) => fetchWithAuth<{ benefit_plans: BenefitPlan[] }>('/hr/benefit-plans', { signal });
export const getPendingVisits = (signal?: AbortSignal) => fetchWithAuth<{ visits: Visit[] }>('/helpdesk/visits', { signal });

export const recordVisit = (data: { clinic: string; visited_on: string; covered_amount: number }) => fetchWithAuth<{ message: string; visit: Visit }>('/visits', { method: 'POST', body: JSON.stringify(data) });
export const getEmployeeVisits = (employeeId: number, signal?: AbortSignal) => fetchWithAuth<{ employee: { id: number; name: string }; visits: Visit[] }>(`/hr/employees/${employeeId}/visits`, { signal });
export const assignBenefitPlan = (userId: number, planId: number) => fetchWithAuth<{ message: string }>('/hr/benefit-assignments', { method: 'POST', body: JSON.stringify({ user_id: userId, benefit_plan_id: planId }) });
export const decideVisit = (visitId: number, status: 'approved' | 'rejected') => fetchWithAuth<{ message: string; visit: Visit }>(`/helpdesk/visits/${visitId}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
