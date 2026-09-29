import { api } from './api'
import type { AuthResponse, User } from '../types/user'

export interface RegisterPayload {
  username: string
  email: string
  password: string
  password_confirmation: string
}

export interface LoginPayload {
  email: string
  password: string
}

export const authService = {
  register: (payload: RegisterPayload) => api.post<AuthResponse>('/api/auth/register', payload),
  login: (payload: LoginPayload) => api.post<AuthResponse>('/api/auth/login', payload),
  logout: () => api.post<{ message: string }>('/api/auth/logout'),
  me: () => api.get<User>('/api/auth/me'),
}
