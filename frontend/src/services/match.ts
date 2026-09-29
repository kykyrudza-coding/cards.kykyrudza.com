import { api } from './api'
import type { MatchData } from '../types/match'

export const matchService = {
  show: (id: number) => api.get<MatchData>(`/api/matches/${id}`),
  hit: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/hit`),
  stand: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/stand`),
  double: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/double`),
  split: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/split`),
  placeBet: (id: number, amount: number, expectedRound: number) =>
    api.post<MatchData>(`/api/matches/${id}/bet`, { amount, expected_round: expectedRound }),
  nextRound: (id: number, expectedRound: number) =>
    api.post<MatchData>(`/api/matches/${id}/next-round`, { expected_round: expectedRound }),
  finish: (id: number) => api.post<MatchData>(`/api/matches/${id}/finish`),
}
