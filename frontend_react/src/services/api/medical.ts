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
