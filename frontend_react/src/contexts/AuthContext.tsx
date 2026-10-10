import { useState, useCallback, useEffect, type ReactNode } from 'react';
import { getStoredToken, setStoredToken, clearStoredToken, login as apiLogin, logout as apiLogout, ApiError, getCurrentUser, UNAUTHORIZED_EVENT } from '../services/api/auth';
import { AuthContext } from './useAuth';
import type { CurrentUser } from '../types';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => getStoredToken());
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [loading, setLoading] = useState(() => Boolean(getStoredToken()));
  const [profileError, setProfileError] = useState<string | null>(null);
  const [loginError, setLoginError] = useState<string | null>(null);

  const [loggingOut, setLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState<string | null>(null);

  const clearSession = useCallback(() => {
    clearStoredToken(); setToken(null); setUser(null); setLoading(false);
    setLoginError(null); setProfileError(null); setLogoutError(null);
  }, []);

  const logout = useCallback(async () => {
    setLoggingOut(true); setLogoutError(null);
    try {
      await apiLogout();
      clearSession();
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) clearSession();
      else setLogoutError(error instanceof Error ? error.message : 'Unable to sign out. Please try again.');
    } finally {
      setLoggingOut(false);
    }
  }, [clearSession]);

  const login = useCallback(async (email: string, password: string) => {
    clearSession();
    try {
      const data = await apiLogin(email, password);
      setStoredToken(data.access_token); setLoading(true); setToken(data.access_token);
    } catch (error) {
      setLoginError(error instanceof Error ? error.message : 'Login failed.');
      throw error;
    }
  }, [clearSession]);

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
    window.addEventListener(UNAUTHORIZED_EVENT, clearSession);
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, clearSession);
  }, [clearSession]);

  return <AuthContext.Provider value={{ token, user, loading, profileError,
    isAuthenticated: Boolean(token), login, logout, loggingOut, logoutError, loginError,
    clearLoginError: () => setLoginError(null) }}>{children}</AuthContext.Provider>;
}
