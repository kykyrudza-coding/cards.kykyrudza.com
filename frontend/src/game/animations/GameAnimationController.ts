import { nextTick } from 'vue'
import { preloadCardImages } from '../cardImages'
import type { MatchData, CardOrHidden } from '../../types/match'
import { AnimationQueue, pause } from './AnimationQueue'
import { CardAnimation } from './CardAnimation'
import { ChipAnimation } from './ChipAnimation'
import type { PresentationEvent, PresentationPhase } from './types'
const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T
const cardId = (player: number, hand: number, card: number) => `p${player}-h${hand}-c${card}`
interface Hooks {
  render: (match: MatchData) => void
  phase: (phase: PresentationPhase) => void
  ready: (match: MatchData) => void
  event: (event: PresentationEvent, match: MatchData) => void
  error: (error: unknown) => void
}
export class GameAnimationController {
  private queue: AnimationQueue
  private latest: MatchData | null = null
  private display: MatchData | null = null
  private completedRound = ''
  private cards: CardAnimation
  private chips: ChipAnimation
  private reduced: () => boolean
  private hooks: Hooks
  constructor(cards: CardAnimation, chips: ChipAnimation, reduced: () => boolean, hooks: Hooks) {
    this.cards = cards
    this.chips = chips
    this.reduced = reduced
    this.hooks = hooks

    this.queue = new AnimationQueue(hooks.error)
  }
  private async render(value: MatchData) {
    this.display = copy(value)
    this.hooks.render(copy(value))
    await nextTick()
  }
  accept(snapshot: MatchData) {
    if (
      this.latest?.id === snapshot.id &&
      this.latest.version === snapshot.version &&
      this.latest.status === snapshot.status
    )
      return
    const previous = this.latest
    this.latest = copy(snapshot)
    if (previous && previous.id !== snapshot.id) {
      this.completedRound = ''
      this.snap(snapshot)
      return
    }
    if (
      document.hidden ||
      (!previous &&
        (snapshot.game.phase === 'round_finished' ||
          snapshot.game.players.some(
            (player) =>
              player.hands.length > 1 || player.hands.some((hand) => hand.cards.length > 2),
          ))) ||
      this.queue.pending > 3 ||
      (previous && snapshot.round > previous.round + 1) ||
      snapshot.status !== 'active'
    ) {
      this.snap(snapshot)
      return
    }
    this.queue.enqueue((signal) => this.present(copy(snapshot), previous, signal))
  }
  snap(snapshot: MatchData) {
    this.queue.cancel()
    this.cards.reset()
    this.latest = copy(snapshot)
    this.display = copy(snapshot)
    this.hooks.render(copy(snapshot))
    if (snapshot.status !== 'active') {
      this.hooks.phase('idle')
      return
    }
    if (snapshot.game.phase === 'round_finished') {
      if (this.completedRound === `${snapshot.id}:${snapshot.round}`) {
        void this.clearTable(snapshot)
        return
      }
      this.hooks.phase('settling')
      if (!document.hidden)
        this.queue.enqueue((signal) => this.finishRound(copy(snapshot), signal, false))
    } else this.hooks.phase(snapshot.game.phase === 'dealer_turn' ? 'dealer' : 'playing')
  }
  private async present(next: MatchData, previous: MatchData | null, signal: AbortSignal) {
    if (
      next.game.phase === 'round_finished' &&
      this.completedRound === `${next.id}:${next.round}`
    ) {
      await this.clearTable(next)
      return
    }
    if (!previous || next.round !== previous.round) await this.initialDeal(next, signal)
    else await this.updateCards(next, signal)
    if (signal.aborted) return
    if (next.game.phase === 'round_finished') await this.finishRound(next, signal, true)
    else {
      await this.render(next)
      this.hooks.phase(next.game.phase === 'dealer_turn' ? 'dealer' : 'playing')
    }
  }
  private async initialDeal(next: MatchData, signal: AbortSignal) {
    this.hooks.phase('dealing')
    await preloadCardImages()
    if (signal.aborted) return
    this.hooks.event('ROUND_RESET', next)
    const stage = copy(next)
    stage.game.dealer = { cards: [], score: null, status: null }
    stage.game.players.forEach((p) =>
      p.hands.forEach((h) => {
        h.cards = []
        h.result = null
        h.profit = null
        h.is_active = false
        h.status = 'playing'
      }),
    )
    await this.render(stage)
    this.cards.reset()
    await this.cards.shuffle(signal)
    for (let index = 0; index < 2; index++) {
      for (const player of next.game.players) {
        const target = stage.game.players.find((p) => p.id === player.id)?.hands[0]
        const card = player.hands[0]?.cards[index]
        if (!target || !card) continue
        target.cards.push(card)
        await this.render(stage)
        await this.cards.deal(cardId(player.id, 0, index), signal)
        this.hooks.event('CARD_DEALT', next)
      }
      const card = next.game.dealer.cards[index]
      if (card) {
        stage.game.dealer.cards.push(index === 1 ? { hidden: true } : card)
        await this.render(stage)
        await this.cards.deal(`dealer-${index}`, signal)
      }
    }
    if (next.game.phase === 'round_finished') {
      this.display = copy(stage)
      await this.updateCards(next, signal)
    }
  }
  private async updateCards(next: MatchData, signal: AbortSignal) {
    const stage = copy(this.display ?? next)
    this.hooks.phase('dealing')
    for (const player of next.game.players) {
      const current = stage.game.players.find((p) => p.id === player.id)
      if (!current) continue
      if (current.hands.length === 1 && player.hands.length === 2) {
        const positions = new Map<string, DOMRect>()
        const first = this.cards.card(cardId(player.id, 0, 0))?.getBoundingClientRect()
        const second = this.cards.card(cardId(player.id, 0, 1))?.getBoundingClientRect()
        if (first) positions.set(cardId(player.id, 0, 0), first)
        if (second) positions.set(cardId(player.id, 1, 0), second)
        current.hands = player.hands.map((h, index) => ({
          ...copy(h),
          cards: current.hands[0]?.cards[index] ? [current.hands[0]!.cards[index]!] : [],
          result: null,
          profit: null,
        }))
        if (next.game.phase !== 'round_finished') current.chips = player.chips
        await this.render(stage)
        await this.cards.split(positions, signal)
        this.hooks.event('PLAYER_SPLIT', next)
      }
      for (let hi = 0; hi < player.hands.length; hi++) {
        const target = player.hands[hi]!
        const shown = current.hands[hi]
        if (!shown) continue
        if (shown.bet !== target.bet) {
          shown.bet = target.bet
          if (next.game.phase !== 'round_finished') current.chips = player.chips
          await this.render(stage)
          this.hooks.event('BET_CHANGED', next)
        }
        for (let ci = shown.cards.length; ci < target.cards.length; ci++) {
          shown.cards.push(target.cards[ci]!)
          await this.render(stage)
          await this.cards.deal(cardId(player.id, hi, ci), signal)
          this.hooks.event('CARD_DEALT', next)
        }
        // Use only the server's score/status, after the last card has landed.
        shown.score = target.score
        shown.status = target.status
        shown.is_active = false
      }
    }
    if (
      stage.game.dealer.cards.some((c) => 'hidden' in c) &&
      next.game.dealer.cards[1] &&
      !('hidden' in next.game.dealer.cards[1])
    ) {
      this.hooks.phase('dealer')
      stage.game.dealer.cards[1] = next.game.dealer.cards[1]
      await this.render(stage)
      await this.cards.flip('dealer-1', signal)
      this.hooks.event('CARD_REVEALED', next)
    }
    for (
      let index = stage.game.dealer.cards.length;
      index < next.game.dealer.cards.length;
      index++
    ) {
      this.hooks.phase('dealer')
      stage.game.dealer.cards.push(next.game.dealer.cards[index] as CardOrHidden)
      await this.render(stage)
      await this.cards.deal(`dealer-${index}`, signal)
    }
    stage.game.dealer.score = next.game.dealer.score
    stage.game.dealer.status = next.game.dealer.status
    await this.render(stage)
  }
  private async finishRound(next: MatchData, signal: AbortSignal, animate: boolean) {
    const key = `${next.id}:${next.round}`
    if (this.completedRound === key) {
      await this.clearTable(next)
      this.hooks.ready(next)
      return
    }
    if (animate) await pause(this.reduced() ? 0.03 : 0.3, signal)
    await this.render(next)
    this.hooks.phase('settling')
    if (animate) this.hooks.event('ROUND_FINISHED', next)
    if (animate) await this.chips.settle(signal)
    await pause(2, signal)
    this.hooks.phase('collecting')
    if (animate) await this.cards.collect(signal)
    this.hooks.event('CARDS_COLLECT', next)
    await this.clearTable(next)
    this.completedRound = key
    await pause(0.9, signal)
    if (!signal.aborted && this.latest?.round === next.round && this.latest.status === 'active')
      this.hooks.ready(next)
  }
  private async clearTable(next: MatchData) {
    const empty = copy(next)
    empty.game.dealer = { cards: [], score: null, status: null }
    empty.game.players.forEach((p) => {
      p.hands = []
    })
    await this.render(empty)
    this.cards.reset()
    this.hooks.phase(next.status === 'active' ? 'betting' : 'idle')
  }
  dispose() {
    this.queue.cancel()
    this.cards.reset()
  }
}
