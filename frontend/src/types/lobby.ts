export type GameType = 'blackjack' | 'durak' | 'poker'

export type LobbyStatus = 'waiting' | 'started' | 'closed'

export interface LobbyPlayer {
  id: number
  username: string
  seat: number
  is_ready: boolean
  is_host: boolean
}

export interface LobbyHost {
  id: number
  username: string
}

export interface Lobby {
  id: number
  code: string
  game_type: GameType
  status: LobbyStatus
  max_players: number
  starting_chips: number
  default_bet: number
  events_enabled: boolean
  is_private: boolean
  match_id: number | null
  host: LobbyHost
  players: LobbyPlayer[]
}

export interface CreateLobbyPayload {
  game_type?: GameType
  max_players?: number
  starting_chips?: number
  default_bet?: number
  events_enabled?: boolean
  is_private?: boolean
  password?: string | null
}

export type LobbyUpdateReason =
  'player_joined' | 'player_left' | 'ready_changed' | 'host_changed' | 'started'

export interface LobbyUpdatedEvent {
  reason: LobbyUpdateReason
  lobby: Lobby
}
