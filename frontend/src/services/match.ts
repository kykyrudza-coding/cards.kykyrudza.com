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
  attack: (id: number, cards: PlayingCard[]) =>
    api.post<MatchData>(`/api/matches/${id}/actions/attack`, { cards }),
  translate: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/translate`),
  defend: (id: number, attack: PlayingCard, defense: PlayingCard) =>
    api.post<MatchData>(`/api/matches/${id}/actions/defend`, { attack, defense }),
  take: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/take`),
  pass: (id: number) => api.post<MatchData>(`/api/matches/${id}/actions/pass`),
}
