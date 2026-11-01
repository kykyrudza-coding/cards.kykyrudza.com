import type { PlayingCard } from '../../types/match'

export const RANKS = ['6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A']
export const SUIT_GLYPH: Record<string, string> = {
  clubs: '♣',
  diamonds: '♦',
  hearts: '♥',
  spades: '♠',
}
const SUIT_ORDER = ['clubs', 'diamonds', 'spades', 'hearts']

export const same = (a: PlayingCard, b: PlayingCard) => a.rank === b.rank && a.suit === b.suit
export const keyOf = (card: PlayingCard) => `${card.rank}|${card.suit}`
export const glyph = (card: PlayingCard) => `${card.rank}${SUIT_GLYPH[card.suit] ?? ''}`
export const strength = (card: PlayingCard) => RANKS.indexOf(card.rank)

/** Non-trump suits first (grouped, low to high), trumps last — the way people sort a hand. */
export function sortHand(cards: PlayingCard[], trumpSuit: string): PlayingCard[] {
  const order = (suit: string) => (suit === trumpSuit ? 99 : SUIT_ORDER.indexOf(suit))
  return [...cards].sort((a, b) => order(a.suit) - order(b.suit) || strength(a) - strength(b))
}
