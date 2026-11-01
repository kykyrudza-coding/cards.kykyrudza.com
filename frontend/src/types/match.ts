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
  /** Set once a rematch was started from this finished match. */
  next_match_id?: number | null
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
  result: 'win' | 'lose' | 'push' | 'blackjack' | 'event' | 'killed' | null
  profit?: number | null
  is_split: boolean
  is_active: boolean
}

export interface BlackjackPlayerView {
  id: number
  username: string | null
  avatar?: string | null
  seat: number
  chips: number
  status: 'active' | 'out'
  hands: BlackjackHandView[]
}

export interface MachineGunEvent {
  type: 'machine_gun'
  /** Players the holder may shoot (everyone else still in the round). */
  targets: number[]
}

export interface MachineGunResult {
  type: 'machine_gun'
  holder_id: number
  mode: 'dealer' | 'player'
  target_id: number | null
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
  /** Private: only present for the viewer who currently holds an event weapon. */
  event?: MachineGunEvent | null
  event_result?: MachineGunResult | null
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

export type DurakLogAction = 'attack' | 'defend' | 'take' | 'pass' | 'translate' | 'bito'

export interface DurakLogEntry {
  user_id: number
  action: DurakLogAction
  cards: PlayingCard[]
  auto: boolean
  round: number
}

/** What the viewer can do with their cards right now — computed by the server. */
export interface DurakHints {
  playable: PlayingCard[]
  defenses: { attack: PlayingCard; with: PlayingCard[] }[]
  translate: PlayingCard[]
}

export interface DurakTableSlot {
  attack: PlayingCard
  defense: PlayingCard | null
}

export interface DurakPlayerView {
  id: number
  username: string | null
  avatar?: string | null
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
  discard_count: number
  /** The players next to the defender, who may add cards. */
  throwers: number[]
  passed: number[]
  /** The defender gave up; the neighbours may still add before the pile is taken. */
  taking: boolean
  attack_limit: number
  hints: DurakHints
  log: DurakLogEntry[]
  turn_deadline: number | null
  turn_seconds: number
  server_time: number
}

export interface DurakMatchData extends MatchBase {
  game_type: 'durak'
  game: DurakGameState
}

// --- Texas Hold'em ----------------------------------------------------------

export type PokerPhase = 'preflop' | 'flop' | 'turn' | 'river' | 'hand_finished' | 'finished'

export type PokerAction = 'fold' | 'check' | 'call' | 'raise' | 'all_in' | 'next_hand' | 'timeout'

export type PokerLogAction =
  | 'small_blind'
  | 'big_blind'
  | 'fold'
  | 'check'
  | 'call'
  | 'bet'
  | 'raise'
  | 'all_in'

export type PokerHandName =
  | 'high_card'
  | 'pair'
  | 'two_pair'
  | 'three_of_a_kind'
  | 'straight'
  | 'flush'
  | 'full_house'
  | 'four_of_a_kind'
  | 'straight_flush'

export interface PokerPlayerView {
  id: number
  username: string | null
  avatar?: string | null
  seat: number
  status: 'active' | 'folded' | 'all_in' | 'out'
  chips: number
  /** Chips committed on the current betting street. */
  bet: number
  total_bet: number
  hand_count: number
  /** Own cards, or everyone still in the hand once a showdown reveals them. */
  hand?: PlayingCard[]
  is_dealer: boolean
  is_small_blind: boolean
  is_big_blind: boolean
  last_action: PokerLogAction | null
  last_amount: number
  /** Best made hand — your own, or everyone's still in at a showdown. */
  hand_name?: PokerHandName
}

export interface PokerResult {
  user_id: number
  amount: number
  hand: PokerHandName | null
  /** The winning five cards (showdown only). */
  cards: PlayingCard[]
}

export type PokerLogEntry =
  | { action: 'street'; phase: PokerPhase }
  | { user_id: number; action: PokerLogAction; amount: number; phase: PokerPhase; auto: boolean }

export interface PokerGameState {
  phase: PokerPhase
  round: number
  small_blind: number
  big_blind: number
  community: PlayingCard[]
  pot: number
  current_bet: number
  to_call: number
  min_raise_to: number
  max_raise_to: number
  players: PokerPlayerView[]
  dealer_id: number
  current_player_id: number | null
  allowed_actions: PokerAction[]
  showdown: boolean
  results: PokerResult[]
  ready: number[]
  winner_id: number | null
  /** Board size when betting closed on an all-in runout; the rest is revealed street by street. */
  runout_from: number | null
  log: PokerLogEntry[]
  turn_deadline: number | null
  turn_seconds: number
  hand_ended_at: number | null
  next_hand_at: number | null
  server_time: number
}

export interface PokerMatchData extends MatchBase {
  game_type: 'poker'
  game: PokerGameState
}

// --- Union ---------------------------------------------------------------

export type MatchData = BlackjackMatchData | DurakMatchData | PokerMatchData
