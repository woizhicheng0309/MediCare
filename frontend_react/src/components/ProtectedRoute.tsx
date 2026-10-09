import { Navigate, useLocation } from 'react-router-dom';
import { Alert, Box, Button, CircularProgress, Stack } from '@mui/material';
import { useAuth } from '../contexts/useAuth';

export default function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, loading, profileError, user, logout } = useAuth();
  const location = useLocation();
  if (!isAuthenticated) return <Navigate to="/login" state={{ from: location }} replace />;
  if (loading) return <Box sx={{ p: 6, textAlign: 'center' }}><CircularProgress aria-label="Loading account" /></Box>;
  if (profileError || !user) return <Stack spacing={2} sx={{ p: 4 }}>
    <Alert severity="error">{profileError ?? 'Unable to load your account.'}</Alert>
    <Button onClick={logout}>Return to login</Button>
  </Stack>;
  return <>{children}</>;
}
