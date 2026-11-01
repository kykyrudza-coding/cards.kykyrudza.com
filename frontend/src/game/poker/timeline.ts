import type { PokerGameState } from '../../types/match'

// Presentation timeline for the end of a hand. The server only decides the
// outcome and when the next hand may start; how it is *shown* is derived from
// the time since the hand ended, so every client (and a reloaded page) lands on
// the same frame without any extra messages.

/** Seconds until the first runout street is turned over. */
export const FIRST_REVEAL = 1.0
/** Seconds between runout streets (flop → turn → river). */
export const REVEAL_STEP = 1.8
/** Pause after the last card before the winners are announced. */
export const RESULT_PAUSE = 0.7

/** Board sizes revealed one after another during an all-in runout. */
export function runoutStages(runoutFrom: number | null): number[] {
  if (runoutFrom === null || runoutFrom >= 5) return []
  return runoutFrom < 3 ? [3, 4, 5] : Array.from({ length: 5 - runoutFrom }, (_, i) => runoutFrom + 1 + i)
}

const isOver = (game: Pick<PokerGameState, 'phase'>) =>
  game.phase === 'hand_finished' || game.phase === 'finished'

/**
 * @param elapsed seconds since the hand ended
 * @returns how many community cards should be on show right now
 */
export function visibleCommunity(
  game: Pick<PokerGameState, 'phase' | 'community' | 'runout_from' | 'showdown'>,
  elapsed: number,
): number {
  const total = game.community.length
  if (!isOver(game) || !game.showdown) return total
  const stages = runoutStages(game.runout_from)
  if (!stages.length) return total
  let shown = game.runout_from ?? 0
  stages.forEach((size, index) => {
    if (elapsed >= FIRST_REVEAL + index * REVEAL_STEP) shown = size
  })
  return Math.min(shown, total)
}

/** Seconds after the hand ends at which the winners / hand names are announced. */
export function resultsDelay(
  game: Pick<PokerGameState, 'runout_from' | 'showdown'>,
): number {
  if (!game.showdown) return 0.2
  const stages = runoutStages(game.runout_from)
  if (!stages.length) return 1.0
  return FIRST_REVEAL + (stages.length - 1) * REVEAL_STEP + RESULT_PAUSE + 0.6
}

export function resultsVisible(
  game: Pick<PokerGameState, 'phase' | 'runout_from' | 'showdown'>,
  elapsed: number,
): boolean {
  return isOver(game) && elapsed >= resultsDelay(game)
}
