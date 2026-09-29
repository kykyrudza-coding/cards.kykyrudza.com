import { api } from './api'
import type { CreateLobbyPayload, Lobby } from '../types/lobby'
import type { MatchData } from '../types/match'

export const lobbyService = {
  create: (payload: CreateLobbyPayload) => api.post<Lobby>('/api/lobbies', payload),
  show: (code: string) => api.get<Lobby>(`/api/lobbies/${code}`),
  join: (code: string, password?: string | null) =>
    api.post<Lobby>(`/api/lobbies/${code}/join`, { password: password ?? null }),
  leave: (code: string) => api.post<{ message: string }>(`/api/lobbies/${code}/leave`),
  setReady: (code: string, ready: boolean) =>
    api.post<Lobby>(`/api/lobbies/${code}/ready`, { ready }),
  start: (code: string) => api.post<MatchData>(`/api/lobbies/${code}/start`),
}
