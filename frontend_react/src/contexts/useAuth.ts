import { createContext, useContext } from 'react';
import type { CurrentUser } from '../types';
export interface AuthContextValue {
  token: string | null; user: CurrentUser | null; isAuthenticated: boolean;
  loading: boolean; profileError: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void; loginError: string | null; clearLoginError: () => void;
}
export const AuthContext = createContext<AuthContextValue | null>(null);
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
}
