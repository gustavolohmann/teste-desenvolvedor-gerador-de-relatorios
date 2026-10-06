import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router'
import { useAuth } from '../auth/auth-context'
import { writeNotice } from '../auth/session'

export function AppBar() {
  const { logout } = useAuth()
  const navigate = useNavigate()
  const [pending, setPending] = useState(false)

  async function handleLogout() {
    setPending(true)

    try {
      await logout()
      writeNotice('Sessão encerrada.')
      navigate('/login', { replace: true })
    } finally {
      setPending(false)
    }
  }

  return (
    <header className="topbar">
      <nav className="topbar-links" aria-label="Principal">
        <NavLink to="/">Início</NavLink>
        <NavLink to="/clientes">Clientes</NavLink>
        <NavLink to="/cobrancas">Cobranças</NavLink>
      </nav>
      <button className="button button-ghost" type="button" onClick={handleLogout} disabled={pending}>
        {pending ? 'Saindo.' : 'Sair'}
      </button>
    </header>
  )
}
