export type User = {
  id: number
  name: string
  email: string
}

export type LoginResponse = {
  token: string
  data: User
}

export type UserResponse = {
  data: User
}
