const storageKey = 'auth_token'
let notice: string | null = null

export function readToken(): string | null {
  return localStorage.getItem(storageKey)
}

export function writeToken(token: string): void {
  localStorage.setItem(storageKey, token)
}

export function clearToken(): void {
  localStorage.removeItem(storageKey)
}

export function writeNotice(message: string): void {
  notice = message
}

export function readNotice(): string | null {
  return notice
}

export function clearNotice(): void {
  notice = null
}
