import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { useAuth } from '../auth/auth-context'
import { clearNotice, readNotice, writeNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

export function HomePage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [notice] = useState(readNotice)
  const [pending, setPending] = useState(false)

  useEffect(() => {
    clearNotice()
  }, [])

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
    <Sheet
      eyebrow="Cobrança"
      title="Gerador de relatórios"
      lede={user ? `Olá, ${user.name}.` : undefined}
      footerStart="Via de conferência"
      footerEnd="Em operação"
    >
      {notice ? (
        <p className="status" role="status">
          {notice}
        </p>
      ) : null}
      <div className="form-actions">
        <button className="button" type="button" onClick={handleLogout} disabled={pending}>
          {pending ? 'Saindo.' : 'Sair'}
        </button>
      </div>
    </Sheet>
  )
}
