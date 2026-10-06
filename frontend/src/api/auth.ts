import { apiRequest, ApiError } from './client'
import type { LoginResponse, User, UserResponse } from './types'

export async function login(email: string, password: string): Promise<LoginResponse> {
  const result = await apiRequest<LoginResponse>('/api/login', {
    method: 'POST',
    body: { email, password },
  })

  if (!result?.token) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result
}

export async function logout(): Promise<void> {
  await apiRequest<undefined>('/api/logout', { method: 'POST' })
}

export async function currentUser(signal?: AbortSignal): Promise<User> {
  const result = await apiRequest<UserResponse>('/api/user', { signal })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}
