import { createContext, useContext } from 'react'
import type { User } from '../api/types'

export type AuthStatus = 'loading' | 'anonymous' | 'authenticated' | 'error'

export type AuthContextValue = {
  user: User | null
  status: AuthStatus
  error: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth deve ser usado dentro de AuthProvider.')
  }

  return context
}
