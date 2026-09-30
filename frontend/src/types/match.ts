export type MatchStatus = 'active' | 'finished' | 'cancelled'

export interface PlayingCard {
  rank: string
  suit: string
}

export type CardOrHidden = PlayingCard | { hidden: true }

interface MatchBase {
  id: number
  status: MatchStatus
  round: number
  version: number
  host_id: number
  lobby_code?: string
}

// --- Blackjack --------------------------------------------------------------

export type BlackjackPhase =
  'dealing' | 'player_turn' | 'dealer_turn' | 'settling' | 'round_finished'

export type BlackjackAction = 'hit' | 'stand' | 'double' | 'split'

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

export interface BlackjackMatchData extends MatchBase {
  game_type: 'blackjack'
  default_bet?: number
  confirmed_bets?: Record<string, number>
  bet_min?: number
  manual_bets?: boolean
  can_start_next_round?: boolean
  game: BlackjackGameState
}

// --- Дурак (Durak) -----------------------------------------------------------

export type DurakPhase = 'attack' | 'throw_in' | 'finished'

export type DurakAction = 'attack' | 'translate' | 'defend' | 'take' | 'pass'

export interface DurakTableSlot {
  attack: PlayingCard
  defense: PlayingCard | null
}

export interface DurakPlayerView {
  id: number
  username: string | null
  seat: number
  status: 'active' | 'safe'
  hand_count: number
  /** Only present for the viewer's own seat — opponents' hands are hidden. */
  hand?: PlayingCard[]
}

export interface DurakGameState {
  phase: DurakPhase
  round: number
  trump_suit: string
  trump_card: PlayingCard
  deck_count: number
  table: DurakTableSlot[]
  players: DurakPlayerView[]
  attacker_id: number
  defender_id: number
  allowed_actions: DurakAction[]
  loser_id: number | null
}

export interface DurakMatchData extends MatchBase {
  game_type: 'durak'
  game: DurakGameState
}

// --- Union ---------------------------------------------------------------

export type MatchData = BlackjackMatchData | DurakMatchData
