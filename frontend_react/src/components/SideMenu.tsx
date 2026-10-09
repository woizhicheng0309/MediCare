import { Box, List, ListItemButton, ListItemIcon, ListItemText, Typography } from '@mui/material';
import { Link as RouterLink } from 'react-router-dom';
import HealthAndSafetyOutlinedIcon from '@mui/icons-material/HealthAndSafetyOutlined';
import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined';
import { useAuth } from '../contexts/useAuth';

export default function SideMenu() {
  const { user } = useAuth();
  const label = user?.role === 'employee' ? 'My benefits' : user?.role === 'hr' ? 'Company overview' : 'Pending visits';
  return <Box component="aside" sx={{ width: 248, flexShrink: 0, display: { xs: 'none', md: 'block' }, borderRight: 1, borderColor: 'divider', bgcolor: 'background.paper', p: 2 }}>
    <HealthAndSafetyOutlinedIcon color="primary" sx={{ fontSize: 38, mt: 1 }} />
    <Typography variant="h6" sx={{ mt: 1 }}>MediCare</Typography>
    <Typography variant="caption" color="text.secondary">Health Group</Typography>
    <List sx={{ mt: 4 }}><ListItemButton component={RouterLink} to="/" selected>
      <ListItemIcon><DashboardOutlinedIcon /></ListItemIcon><ListItemText primary={label} />
    </ListItemButton></List>
  </Box>;
}
