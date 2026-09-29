import { defineStore } from 'pinia'
import { matchService } from '../services/match'
import { connectEcho, disconnectEcho } from '../realtime/echo'
import { ApiError } from '../services/api'
import type { MatchData } from '../types/match'
import { t } from '../i18n'

let fetchSequence = 0

interface MatchState {
  match: MatchData | null
  loading: boolean
  actionLoading: boolean
  error: string | null
  subscribedId: number | null
}

export const useMatchStore = defineStore('match', {
  state: (): MatchState => ({
    match: null,
    loading: false,
    actionLoading: false,
    error: null,
    subscribedId: null,
  }),

  actions: {
    async fetchMatch(id: number) {
      const sequence = ++fetchSequence
      this.loading = true
      this.error = null
      try {
        const incoming = await matchService.show(id)
        if (sequence === fetchSequence) this.acceptMatch(incoming)
      } catch (err) {
        if (sequence === fetchSequence)
          this.error = err instanceof ApiError ? err.message : t('errors.loadMatchFailed')
        throw err
      } finally {
        if (sequence === fetchSequence) this.loading = false
      }
    },

    async hit() {
      await this.runAction(() => matchService.hit(this.requireId()))
    },

    async stand() {
      await this.runAction(() => matchService.stand(this.requireId()))
    },

    async double() {
      await this.runAction(() => matchService.double(this.requireId()))
    },

    async split() {
      await this.runAction(() => matchService.split(this.requireId()))
    },

    async placeBet(amount: number) {
      const round = this.match?.round
      if (round === undefined) return
      await this.runAction(() => matchService.placeBet(this.requireId(), amount, round))
    },

    async nextRound() {
      const round = this.match?.round
      if (round === undefined) return
      await this.runAction(() => matchService.nextRound(this.requireId(), round))
    },

    async finishMatch() {
      await this.runAction(() => matchService.finish(this.requireId()))
    },

    async runAction(fn: () => Promise<MatchData>) {
      if (this.actionLoading) return
      const id = this.match?.id
      this.actionLoading = true
      this.error = null
      try {
        const incoming = await fn()
        if (this.match?.id === id) this.acceptMatch(incoming)
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.actionFailed')
        throw err
      } finally {
        this.actionLoading = false
      }
    },

    requireId(): number {
      if (!this.match) {
        throw new Error(t('errors.noActiveMatch'))
      }
      return this.match.id
    },

    acceptMatch(incoming: MatchData) {
      // A slower refetch must not replace a newer action/realtime snapshot.
      if (this.match?.id === incoming.id && this.match.version > incoming.version) return
      this.match = incoming
    },

    connectRealtime(id: number) {
      if (this.subscribedId === id) {
        return
      }

      this.disconnectRealtime()

      const echo = connectEcho()
      echo.private(`match.${id}`).listen('.MatchUpdated', () => {
        this.fetchMatch(id).catch(() => undefined)
      })

      this.subscribedId = id
    },

    disconnectRealtime() {
      if (this.subscribedId) {
        disconnectEcho()
        this.subscribedId = null
      }
    },
  },
})
