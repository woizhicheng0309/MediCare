import { createBrowserRouter, Navigate } from 'react-router-dom';
import MainLayout from '../layouts/MainLayout';
import ProtectedRoute from '../components/ProtectedRoute';
import Dashboard from '../pages/Home/Dashboard';
import LoginPage from '../pages/Login/Login';

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/', element: <ProtectedRoute><MainLayout /></ProtectedRoute>, children: [{ index: true, element: <Dashboard /> }] },
  { path: '*', element: <Navigate to="/" replace /> },
]);
