import { useState, type FormEvent } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Alert, Box, Button, Link, Paper, Stack, TextField, Typography } from '@mui/material';
import { alpha } from '@mui/material/styles';
import HealthAndSafetyOutlinedIcon from '@mui/icons-material/HealthAndSafetyOutlined';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import { useAuth } from '../../contexts/useAuth';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login, loginError, clearLoginError, logoutError } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname ?? '/';

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); clearLoginError(); setLoading(true);
    try { await login(email.trim(), password); setPassword(''); navigate(from, { replace: true }); }
    catch { /* The authentication provider supplies the visible error. */ }
    finally { setLoading(false); }
  }

  return <Box sx={{ minHeight: '100dvh', display: 'grid', placeItems: 'center', p: 3, bgcolor: 'background.default' }}>
    <Paper variant="outlined" sx={{ width: '100%', maxWidth: 440, p: { xs: 3, sm: 5 }, borderRadius: 3 }}>
      <Stack spacing={3}>
        <HealthAndSafetyOutlinedIcon sx={{ fontSize: 44, color: 'primary.main' }} />
        <Box><Typography variant="h5" component="h1">MediCare Health Group</Typography>
          <Typography color="text.secondary" sx={{ mt: 1 }}>Sign in to your medical benefits portal.</Typography></Box>
        <Box
          component="aside"
          aria-labelledby="demo-access-title"
          sx={(theme) => ({
            p: 2,
            border: '1px solid',
            borderColor: alpha(theme.palette.primary.main, 0.3),
            borderRadius: 2,
            bgcolor: alpha(theme.palette.primary.main, 0.06),
          })}
        >
          <Typography id="demo-access-title" component="h2" variant="subtitle2" sx={{ fontWeight: 700 }}>
            Trying the demo?
          </Typography>
          <Link
            href="https://gitlab.com/woizhicheng0309/medicare/-/blob/main/README.md#live-demo"
            target="_blank"
            rel="noopener noreferrer"
            lang="zh-Hant"
            aria-describedby="demo-access-description"
            underline="always"
            sx={{
              display: 'flex',
              alignItems: 'center',
              gap: 1,
              width: '100%',
              minHeight: 44,
              my: 0.5,
              color: 'primary.dark',
              fontSize: { xs: '0.875rem', sm: '1rem' },
              fontWeight: 700,
              textDecoration: 'underline',
              textUnderlineOffset: '3px',
              '&::before': { display: 'none' },
            }}
          >
            <span>Demo用帳號ID與密碼請點這裡</span>
            <OpenInNewIcon fontSize="small" sx={{ flexShrink: 0 }} />
          </Link>
          <Typography id="demo-access-description" variant="caption" color="text.secondary">
            Employee, HR and Helpdesk accounts on GitLab. Opens in a new tab.
          </Typography>
        </Box>
        <Box component="form" onSubmit={handleSubmit}>
          <Stack spacing={2.5}>
            {logoutError && <Alert severity="warning">{logoutError}</Alert>}
            {loginError && <Alert severity="error">{loginError}</Alert>}
            <TextField label="Email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" required fullWidth />
            <TextField label="Password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required fullWidth />
            <Button type="submit" variant="contained" size="large" disabled={loading}>{loading ? 'Signing in...' : 'Sign in'}</Button>
          </Stack>
        </Box>
      </Stack>
    </Paper>
  </Box>;
}
