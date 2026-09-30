import { api } from './api'
import type { User } from '../types/user'

export const profileService = {
  update: (username: string) => api.patch<User>('/api/profile', { username }),
}
