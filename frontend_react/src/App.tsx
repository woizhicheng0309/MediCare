import { RouterProvider } from 'react-router-dom';
import { router } from './routes';
import { AuthProvider } from './contexts/AuthContext';
import AppTheme from './shared-theme/AppTheme';

export default function App() {
  return <AppTheme><AuthProvider><RouterProvider router={router} /></AuthProvider></AppTheme>;
}
