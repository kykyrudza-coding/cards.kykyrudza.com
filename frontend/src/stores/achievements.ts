import { defineStore } from 'pinia'
import { achievementsService } from '../services/achievements'
import type { Achievement } from '../types/achievement'

interface AchievementsState {
  catalog: Achievement[]
  loading: boolean
  queue: Achievement[]
  current: Achievement | null
  polling: boolean
}

let pollTimer: ReturnType<typeof setInterval> | null = null

export const useAchievementsStore = defineStore('achievements', {
  state: (): AchievementsState => ({
    catalog: [],
    loading: false,
    queue: [],
    current: null,
    polling: false,
  }),

  actions: {
    async fetchCatalog() {
      this.loading = true
      try {
        const { achievements } = await achievementsService.index()
        this.catalog = achievements
      } finally {
        this.loading = false
      }
    },

    // Best-effort notification poll — failures here must never surface as
    // user-facing errors, this is purely cosmetic on top of real gameplay.
    async checkUnseen() {
      try {
        const unseen = await achievementsService.unseen()
        if (unseen.length === 0) return
        this.queue.push(...unseen)
        this.advance()
        // The catalog page may already be open — keep its unlocked_at in sync.
        for (const achievement of unseen) {
          const entry = this.catalog.find((a) => a.key === achievement.key)
          if (entry) entry.unlocked_at = achievement.unlocked_at
        }
      } catch {
        /* silent */
      }
    },

    advance() {
      if (this.current || this.queue.length === 0) return
      this.current = this.queue.shift() ?? null
    },

    dismissCurrent() {
      this.current = null
      // A short gap so back-to-back unlocks read as distinct splashes.
      window.setTimeout(() => this.advance(), 400)
    },

    startPolling() {
      if (this.polling) return
      this.polling = true
      void this.checkUnseen()
      pollTimer = window.setInterval(() => void this.checkUnseen(), 20000)
    },

    stopPolling() {
      this.polling = false
      if (pollTimer) {
        window.clearInterval(pollTimer)
        pollTimer = null
      }
      this.queue = []
      this.current = null
    },
  },
})
