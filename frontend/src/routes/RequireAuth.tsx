import { Navigate, Outlet } from 'react-router'
import { useAuth } from '../auth/auth-context'
import { AppBar } from '../components/AppBar'
import { Sheet } from '../components/Sheet'

export function RequireAuth() {
  const { status, error } = useAuth()

  if (status === 'loading') {
    return (
      <Sheet eyebrow="Cobrança" title="Gerador de relatórios" footerStart="Via de conferência" footerEnd="Aguardando">
        <p className="status">Consultando a sessão.</p>
      </Sheet>
    )
  }

  if (status === 'error') {
    return (
      <Sheet eyebrow="Cobrança" title="Gerador de relatórios" footerStart="Via de conferência" footerEnd="Aguardando API">
        <p className="alert" role="alert">
          {error}
        </p>
      </Sheet>
    )
  }

  if (status !== 'authenticated') {
    return <Navigate to="/login" replace />
  }

  return (
    <>
      <AppBar />
      <Outlet />
    </>
  )
}
