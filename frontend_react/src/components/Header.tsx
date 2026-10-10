import { Alert, Box, Button, Stack, Typography } from '@mui/material';
import { useAuth } from '../contexts/useAuth';

export default function Header() {
  const { user, logout, loggingOut, logoutError } = useAuth();
  return <Box component="header" sx={{ p: 2.5, borderBottom: 1, borderColor: 'divider', bgcolor: 'background.paper' }}>
    <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }} spacing={2}>
      <Box><Typography sx={{ fontWeight: 600 }}>{user?.name}</Typography><Typography variant="caption" color="text.secondary">{user?.role.toUpperCase()}</Typography></Box>
      <Button onClick={logout} disabled={loggingOut}>{loggingOut ? 'Signing out...' : 'Sign out'}</Button>
    </Stack>
    {logoutError && <Alert severity="error" sx={{ mt: 2 }}>{logoutError}</Alert>}
  </Box>;
}
