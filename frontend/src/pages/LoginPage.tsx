import { useEffect, useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/auth-context'
import { clearNotice, readNotice, writeNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

export function LoginPage() {
  const { status, login } = useAuth()
  const navigate = useNavigate()
  const [notice] = useState(readNotice)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState(false)

  useEffect(() => {
    clearNotice()
  }, [])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPending(true)
    setError(null)
    setSubmitted(true)

    try {
      await login(email, password)
      writeNotice('Sessão iniciada.')
      navigate('/', { replace: true })
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'A API não respondeu.')
    } finally {
      setPending(false)
    }
  }

  if (status === 'loading') {
    return (
      <Sheet eyebrow="Cobrança" title="Entrar" footerStart="Acesso restrito" footerEnd="Aguardando">
        <p className="status">Consultando a sessão.</p>
      </Sheet>
    )
  }

  if (status === 'authenticated' && !submitted) {
    return <Navigate to="/" replace />
  }

  return (
    <Sheet
      eyebrow="Cobrança"
      title="Entrar"
      lede="Use o acesso informado para a equipe."
      footerStart="Acesso restrito"
      footerEnd={pending ? 'Conferindo' : 'Aguardando'}
    >
      <form className="form" onSubmit={handleSubmit}>
        {notice ? (
          <p className="status" role="status">
            {notice}
          </p>
        ) : null}
        {error ? (
          <p className="alert" role="alert">
            {error}
          </p>
        ) : null}
        <label className="field" htmlFor="email">
          E-mail
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </label>
        <label className="field" htmlFor="password">
          Senha
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
          />
        </label>
        <div className="form-actions">
          <button className="button" type="submit" disabled={pending}>
            {pending ? 'Entrando.' : 'Entrar'}
          </button>
        </div>
      </form>
    </Sheet>
  )
}
