export interface User {
  id: number
  username: string
  email: string
  avatar: string | null
}

export interface AuthResponse {
  user: User
  token: string
}
