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

export async function apiGet<T>(path: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(`${apiBaseUrl()}${path}`, {
    signal,
    headers: {
      Accept: 'application/json',
    },
  })

  if (!response.ok) {
    throw new ApiError('A API não respondeu.', response.status)
  }

  return response.json() as Promise<T>
}

export function getHealth(signal?: AbortSignal): Promise<{ status: string }> {
  return apiGet('/api/health', signal)
}
