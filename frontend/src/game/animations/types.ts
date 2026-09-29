export type PresentationPhase =
  'idle' | 'betting' | 'dealing' | 'playing' | 'dealer' | 'settling' | 'collecting'
export type PresentationEvent =
  | 'CARD_DEALT'
  | 'CARD_REVEALED'
  | 'PLAYER_SPLIT'
  | 'BET_CHANGED'
  | 'ROUND_FINISHED'
  | 'CARDS_COLLECT'
  | 'ROUND_RESET'
