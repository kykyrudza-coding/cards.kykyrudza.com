import { api } from './api'
import type { Achievement } from '../types/achievement'

export const achievementsService = {
  index: () => api.get<{ achievements: Achievement[] }>('/api/achievements'),
  unseen: () => api.get<Achievement[]>('/api/achievements/unseen'),
}
