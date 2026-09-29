export type MatchStatus = 'active' | 'finished' | 'cancelled'

export type BlackjackPhase =
  'dealing' | 'player_turn' | 'dealer_turn' | 'settling' | 'round_finished'

export type BlackjackAction = 'hit' | 'stand' | 'double' | 'split'

export interface PlayingCard {
  rank: string
  suit: string
}

export type CardOrHidden = PlayingCard | { hidden: true }

export interface BlackjackHandView {
  cards: PlayingCard[]
  score: number
  bet: number
  status: 'playing' | 'stood' | 'bust' | 'blackjack' | 'finished'
  result: 'win' | 'lose' | 'push' | 'blackjack' | null
  profit?: number | null
  is_split: boolean
  is_active: boolean
}

export interface BlackjackPlayerView {
  id: number
  username: string | null
  seat: number
  chips: number
  status: 'active' | 'out'
  hands: BlackjackHandView[]
}

export interface BlackjackGameState {
  phase: BlackjackPhase
  round: number
  dealer: {
    cards: CardOrHidden[]
    score: number | null
    status?: 'stood' | 'bust' | 'blackjack' | null
  }
  players: BlackjackPlayerView[]
  current_player_id: number | null
  current_hand_index: number | null
  allowed_actions: BlackjackAction[]
}

export interface MatchData {
  id: number
  game_type: string
  status: MatchStatus
  round: number
  version: number
  host_id: number
  default_bet?: number
  confirmed_bets?: Record<string, number>
  bet_min?: number
  manual_bets?: boolean
  can_start_next_round?: boolean
  lobby_code?: string
  game: BlackjackGameState
}
