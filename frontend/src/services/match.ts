import { api } from './api'
import type { MatchData, PlayingCard } from '../types/match'

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
  machineGun: (id: number, mode: 'dealer' | 'player', targetId?: number) =>
    api.post<MatchData>(`/api/matches/${id}/actions/machine-gun`, { mode, target_id: targetId }),
  attack: (id: number, cards: PlayingCard[]) =>
    api.post<MatchData>(`/api/matches/${id}/actions/attack`, { cards }),
  translate: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/translate`),
  defend: (id: number, attack: PlayingCard, defense: PlayingCard) =>
    api.post<MatchData>(`/api/matches/${id}/actions/defend`, { attack, defense }),
  take: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/take`),
  pass: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/pass`),
  fold: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/fold`),
  check: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/check`),
  call: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/call`),
  raise: (id: number, amount: number) =>
    api.post<MatchData>(`/api/matches/${id}/actions/raise`, { amount }),
  allIn: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/all-in`),
  nextHand: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/next-hand`),
}
