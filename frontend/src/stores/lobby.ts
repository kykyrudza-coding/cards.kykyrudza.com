import { defineStore } from 'pinia'
import { lobbyService } from '../services/lobby'
import { connectEcho, disconnectEcho } from '../realtime/echo'
import { ApiError } from '../services/api'
import type { CreateLobbyPayload, Lobby, LobbyUpdatedEvent } from '../types/lobby'
import type { MatchData } from '../types/match'
import { t } from '../i18n'

interface LobbyState {
  currentLobby: Lobby | null
  loading: boolean
  error: string | null
  subscribedCode: string | null
}

export const useLobbyStore = defineStore('lobby', {
  state: (): LobbyState => ({
    currentLobby: null,
    loading: false,
    error: null,
    subscribedCode: null,
  }),

  actions: {
    async createLobby(payload: CreateLobbyPayload) {
      this.loading = true
      this.error = null
      try {
        this.currentLobby = await lobbyService.create(payload)
        return this.currentLobby
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.createLobbyFailed')
        throw err
      } finally {
        this.loading = false
      }
    },

    async joinLobby(code: string, password?: string | null) {
      this.loading = true
      this.error = null
      try {
        this.currentLobby = await lobbyService.join(code, password)
        return this.currentLobby
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.joinLobbyFailed')
        throw err
      } finally {
        this.loading = false
      }
    },

    async leaveLobby(code: string) {
      this.loading = true
      this.error = null
      try {
        await lobbyService.leave(code)
        this.currentLobby = null
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.leaveLobbyFailed')
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchLobby(code: string) {
      this.loading = true
      this.error = null
      try {
        this.currentLobby = await lobbyService.show(code)
        return this.currentLobby
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.lobbyNotFound')
        throw err
      } finally {
        this.loading = false
      }
    },

    async setReady(code: string, ready: boolean) {
      this.loading = true
      this.error = null
      try {
        this.currentLobby = await lobbyService.setReady(code, ready)
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.readyFailed')
        throw err
      } finally {
        this.loading = false
      }
    },

    async startLobby(code: string): Promise<MatchData> {
      this.loading = true
      this.error = null
      try {
        return await lobbyService.start(code)
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('errors.startLobbyFailed')
        throw err
      } finally {
        this.loading = false
      }
    },

    connectRealtime(code: string) {
      if (this.subscribedCode === code) {
        return
      }

      this.disconnectRealtime()

      const echo = connectEcho()
      echo.private(`lobby.${code}`).listen('.LobbyUpdated', (event: LobbyUpdatedEvent) => {
        this.currentLobby = event.lobby
      })

      this.subscribedCode = code
    },

    disconnectRealtime() {
      if (this.subscribedCode) {
        disconnectEcho()
        this.subscribedCode = null
      }
    },
  },
})
