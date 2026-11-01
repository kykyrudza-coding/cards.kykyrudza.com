import { api } from './api'
import type { CreateLobbyPayload, GameType, Lobby } from '../types/lobby'
import type { MatchData } from '../types/match'

export interface OpenLobby {
  code: string
  game_type: GameType
  max_players: number
  players_count: number
  host: { username: string; avatar: string | null }
}

export const lobbyService = {
  open: () => api.get<{ data: OpenLobby[] }>('/api/lobbies'),
  create: (payload: CreateLobbyPayload) => api.post<Lobby>('/api/lobbies', payload),
  show: (code: string) => api.get<Lobby>(`/api/lobbies/${code}`),
  join: (code: string, password?: string | null) =>
    api.post<Lobby>(`/api/lobbies/${code}/join`, { password: password ?? null }),
  leave: (code: string) => api.post<{ message: string }>(`/api/lobbies/${code}/leave`),
  setReady: (code: string, ready: boolean) =>
    api.post<Lobby>(`/api/lobbies/${code}/ready`, { ready }),
  start: (code: string) => api.post<MatchData>(`/api/lobbies/${code}/start`),
}
