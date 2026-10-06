import { Navigate, Route, Routes } from 'react-router'
import { CustomerDetailPage } from './pages/CustomerDetailPage'
import { CustomerFormPage } from './pages/CustomerFormPage'
import { CustomerListPage } from './pages/CustomerListPage'
import { HomePage } from './pages/HomePage'
import { LoginPage } from './pages/LoginPage'
import { RequireAuth } from './routes/RequireAuth'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<RequireAuth />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/clientes" element={<CustomerListPage />} />
        <Route path="/clientes/novo" element={<CustomerFormPage />} />
        <Route path="/clientes/:id" element={<CustomerDetailPage />} />
        <Route path="/clientes/:id/editar" element={<CustomerFormPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
