import { useState, useCallback, useEffect, type ReactNode } from 'react';
import { getStoredToken, setStoredToken, clearStoredToken, login as apiLogin, getCurrentUser, UNAUTHORIZED_EVENT } from '../services/api/auth';
import { AuthContext } from './useAuth';
import type { CurrentUser } from '../types';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => getStoredToken());
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [loading, setLoading] = useState(() => Boolean(getStoredToken()));
  const [profileError, setProfileError] = useState<string | null>(null);
  const [loginError, setLoginError] = useState<string | null>(null);

  const logout = useCallback(() => {
    clearStoredToken(); setToken(null); setUser(null); setLoading(false);
    setLoginError(null); setProfileError(null);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    logout();
    try {
      const data = await apiLogin(email, password);
      setStoredToken(data.access_token); setLoading(true); setToken(data.access_token);
    } catch (error) {
      setLoginError(error instanceof Error ? error.message : 'Login failed.');
      throw error;
    }
  }, [logout]);

  useEffect(() => {
    if (!token) return;
    const controller = new AbortController();
    getCurrentUser(controller.signal)
      .then((currentUser) => { setUser(currentUser); setProfileError(null); })
      .catch((error: unknown) => {
        if (!controller.signal.aborted) setProfileError(error instanceof Error ? error.message : 'Unable to load your account.');
      })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [token]);

  useEffect(() => {
    window.addEventListener(UNAUTHORIZED_EVENT, logout);
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, logout);
  }, [logout]);

  return <AuthContext.Provider value={{ token, user, loading, profileError,
    isAuthenticated: Boolean(token), login, logout, loginError,
    clearLoginError: () => setLoginError(null) }}>{children}</AuthContext.Provider>;
}
