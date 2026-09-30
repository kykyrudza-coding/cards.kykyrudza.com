import { defineStore } from 'pinia'
import { statisticsService } from '../services/statistics'
import type { Statistics } from '../types/statistics'

interface StatisticsState {
  data: Statistics | null
  loading: boolean
  error: string | null
}

export const useStatisticsStore = defineStore('statistics', {
  state: (): StatisticsState => ({
    data: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetch() {
      this.loading = true
      this.error = null
      try {
        this.data = await statisticsService.show()
      } catch {
        this.error = null // surfaced as an EmptyState fallback, not a hard error
      } finally {
        this.loading = false
      }
    },
  },
})
