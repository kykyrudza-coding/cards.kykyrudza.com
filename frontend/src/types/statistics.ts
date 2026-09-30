export interface StatisticsOverview {
  played: number
  won: number
  losses: number
  win_rate: number | null
  current_win_streak: number
  best_win_streak: number
  peak_match_chips: number
}

export interface BlackjackStatistics {
  played: number
  won: number
  losses: number
  win_rate: number | null
  blackjacks_hit: number
  splits_performed: number
  dealer_busts_witnessed: number
}

export interface DurakStatistics {
  played: number
  survived: number
  losses: number
  win_rate: number | null
}

export interface Statistics {
  overview: StatisticsOverview
  blackjack: BlackjackStatistics
  durak: DurakStatistics
}
