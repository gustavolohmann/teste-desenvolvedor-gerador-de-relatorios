import { readToken } from '../auth/session'

export class ApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

function apiBaseUrl(): string {
  const configured = import.meta.env.VITE_API_URL?.replace(/\/$/, '')

  return configured || 'http://localhost:8000'
}

async function errorMessage(response: Response): Promise<string> {
  const body: unknown = await response.json().catch(() => null)

  if (body && typeof body === 'object' && 'errors' in body) {
    const errors = body.errors
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors)[0]
      if (Array.isArray(first) && typeof first[0] === 'string') {
        return first[0]
      }
    }
  }

  if (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string') {
    return body.message
  }

  return 'A API não respondeu.'
}

export async function apiRequest<T>(
  path: string,
  options: {
    method?: string
    body?: unknown
    signal?: AbortSignal
  } = {},
): Promise<T | undefined> {
  const headers = new Headers({
    Accept: 'application/json',
  })
  const token = readToken()

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }

  const response = await fetch(`${apiBaseUrl()}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    signal: options.signal,
  })

  if (!response.ok) {
    throw new ApiError(await errorMessage(response), response.status)
  }

  if (response.status === 204) {
    return undefined
  }

  return response.json() as Promise<T>
}

export async function apiBlob(path: string): Promise<{ blob: Blob; filename: string }> {
  const headers = new Headers({
    Accept: 'text/csv',
  })
  const token = readToken()

  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }

  const response = await fetch(`${apiBaseUrl()}${path}`, { headers })

  if (!response.ok) {
    throw new ApiError(await errorMessage(response), response.status)
  }

  const disposition = response.headers.get('Content-Disposition') ?? ''
  const match = /filename="([^"]+)"/.exec(disposition) ?? /filename=([^;]+)/.exec(disposition)

  return {
    blob: await response.blob(),
    filename: match?.[1]?.trim() ?? 'faturamento.csv',
  }
}
