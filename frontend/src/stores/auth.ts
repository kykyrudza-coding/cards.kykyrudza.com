import { defineStore } from 'pinia'
import { authService, type LoginPayload, type RegisterPayload } from '../services/auth'
import { ApiError, getToken, setToken } from '../services/api'
import type { User } from '../types/user'
import { t } from '../i18n'

interface AuthState {
  user: User | null
  token: string | null
  loading: boolean
  error: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: getToken(),
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => state.token !== null,
  },

  actions: {
    async register(payload: RegisterPayload) {
      this.loading = true
      this.error = null
      try {
        const { user, token } = await authService.register(payload)
        this.user = user
        this.token = token
        setToken(token)
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('auth.register.genericError')
        throw err
      } finally {
        this.loading = false
      }
    },

    async login(payload: LoginPayload) {
      this.loading = true
      this.error = null
      try {
        const { user, token } = await authService.login(payload)
        this.user = user
        this.token = token
        setToken(token)
      } catch (err) {
        this.error = err instanceof ApiError ? err.message : t('auth.login.genericError')
        throw err
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      try {
        if (this.token) {
          await authService.logout().catch(() => undefined)
        }
      } finally {
        this.user = null
        this.token = null
        setToken(null)
        this.loading = false
      }
    },

    async fetchMe() {
      if (!this.token) {
        return
      }
      this.loading = true
      try {
        this.user = await authService.me()
      } catch (err) {
        if (err instanceof ApiError && err.status === 401) {
          this.user = null
          this.token = null
          setToken(null)
        }
        throw err
      } finally {
        this.loading = false
      }
    },
  },
})
