import { API_BASE_URL, JSON_HEADERS } from './base';
import type { CurrentUser } from '../../types';

const AUTH_TOKEN_KEY = 'medicare_access_token';
export const UNAUTHORIZED_EVENT = 'auth:unauthorized';
export interface LoginResponse { access_token: string; token_type: string; }
export class ApiError extends Error {
  status: number;
  // Retain Laravel field errors so forms can display them beside their inputs.
  errors: Record<string, string[]>;
  constructor(message: string, status: number, errors: Record<string, string[]> = {}) { super(message); this.name = 'ApiError'; this.status = status; this.errors = errors; }
}
// Match the Demo's token persistence across browser sessions.
export function getStoredToken(): string | null { return localStorage.getItem(AUTH_TOKEN_KEY); }
export function setStoredToken(token: string): void { localStorage.setItem(AUTH_TOKEN_KEY, token); }
export function clearStoredToken(): void { localStorage.removeItem(AUTH_TOKEN_KEY); }

async function readResponse<T>(response: Response): Promise<T> {
  const data = await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401) {
      clearStoredToken();
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
    }
    const validationError = data?.errors ? Object.values(data.errors).flat()[0] : null;
    throw new ApiError(String(validationError ?? data?.message ?? data?.error ?? 'Request failed.'), response.status, data?.errors ?? {});
  }
  return data as T;
}

export async function login(email: string, password: string): Promise<LoginResponse> {
  const response = await fetch(`${API_BASE_URL}/login`, {
    method: 'POST', headers: { ...JSON_HEADERS, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const data = await readResponse<LoginResponse>(response);
  if (!data.access_token) throw new Error('The login response did not include an access token.');
  return data;
}

export async function fetchWithAuth<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  const token = getStoredToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (options.body) headers.set('Content-Type', 'application/json');
  return readResponse<T>(await fetch(`${API_BASE_URL}${endpoint}`, { ...options, headers }));
}
export function getCurrentUser(signal?: AbortSignal): Promise<CurrentUser> {
  return fetchWithAuth<CurrentUser>('/me', { signal });
}

// Use the captured token because local storage has already been cleared.
export async function logout(token: string): Promise<{ message: string }> {
  const response = await fetch(`${API_BASE_URL}/logout`, {
    method: 'POST', headers: { ...JSON_HEADERS, Authorization: `Bearer ${token}` },
  });
  const data = await response.json().catch(() => null);
  if (!response.ok) throw new ApiError(data?.message ?? 'Unable to revoke the session.', response.status);
  return data as { message: string };
}
