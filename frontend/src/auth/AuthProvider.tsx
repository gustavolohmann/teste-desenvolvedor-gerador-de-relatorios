import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { currentUser, login as loginRequest, logout as logoutRequest } from '../api/auth'
import { ApiError } from '../api/client'
import type { User } from '../api/types'
import { AuthContext, type AuthContextValue, type AuthStatus } from './auth-context'
import { clearToken, readToken, writeToken } from './session'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [status, setStatus] = useState<AuthStatus>(() => (readToken() ? 'loading' : 'anonymous'))
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!readToken()) {
      return
    }

    const controller = new AbortController()

    currentUser(controller.signal)
      .then((nextUser) => {
        if (controller.signal.aborted) {
          return
        }

        setUser(nextUser)
        setStatus('authenticated')
      })
      .catch((reason: unknown) => {
        if (controller.signal.aborted) {
          return
        }

        if (reason instanceof ApiError && reason.status === 401) {
          clearToken()
          setUser(null)
          setStatus('anonymous')
          return
        }

        setError('A API não respondeu. Inicie o backend e atualize a página.')
        setStatus('error')
      })

    return () => controller.abort()
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      status,
      error,
      async login(email, password) {
        const result = await loginRequest(email, password)
        writeToken(result.token)
        setUser(result.data)
        setStatus('authenticated')
        setError(null)
      },
      async logout() {
        try {
          await logoutRequest()
        } finally {
          clearToken()
          setUser(null)
          setStatus('anonymous')
        }
      },
    }),
    [error, status, user],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

