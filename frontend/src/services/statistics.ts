import { api } from './api'
import type { Statistics } from '../types/statistics'

export const statisticsService = {
  show: () => api.get<Statistics>('/api/statistics'),
}
