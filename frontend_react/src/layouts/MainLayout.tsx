import { Outlet } from 'react-router-dom';
import { Box } from '@mui/material';
import Header from '../components/Header';
import SideMenu from '../components/SideMenu';

export default function MainLayout() {
  return <Box sx={{ minHeight: '100dvh', display: 'flex', bgcolor: 'background.default' }}>
    <SideMenu />
    <Box sx={{ flex: 1, minWidth: 0 }}>
      <Header />
      <Box component="main" sx={{ p: { xs: 2, md: 4 }, maxWidth: 1320, mx: 'auto' }}><Outlet /></Box>
    </Box>
  </Box>;
}
