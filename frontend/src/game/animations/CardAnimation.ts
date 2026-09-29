import { gsap } from 'gsap'
import { pause, playTimeline } from './AnimationQueue'
export class CardAnimation {
  private root: HTMLElement
  private reduced: () => boolean
  private sound: (id: 'deal' | 'flip' | 'collect' | 'shuffle') => void
  constructor(
    root: HTMLElement,
    reduced: () => boolean,
    sound: (id: 'deal' | 'flip' | 'collect' | 'shuffle') => void,
  ) {
    this.root = root
    this.reduced = reduced
    this.sound = sound
  }
  card(id: string) {
    return this.root.querySelector<HTMLElement>(`[data-card-id="${id}"]`)
  }
  async deal(id: string, signal: AbortSignal) {
    const card = this.card(id)
    if (card)
      await Promise.all(
        [...card.querySelectorAll('img')].map((image) => image.decode().catch(() => undefined)),
      )
    if (signal.aborted) throw new DOMException('Animation cancelled', 'AbortError')
    if (!card || !card.getClientRects().length) {
      await pause(0.04, signal)
      return
    }
    const source = this.root.querySelector<HTMLElement>('[data-deck]')?.getBoundingClientRect()
    const target = card.getBoundingClientRect()
    if (!source || this.reduced()) {
      this.sound('deal')
      await pause(0.025, signal)
      return
    }
    const dx = source.left + source.width / 2 - target.left - target.width / 2
    const dy = source.top + source.height / 2 - target.top - target.height / 2
    const tilt = ([...id].reduce((sum, c) => sum + c.charCodeAt(0), 0) % 5) - 2
    const timeline = gsap
      .timeline({ paused: true })
      .fromTo(
        card,
        { x: dx, y: dy, z: 40, rotationZ: -12, rotationX: 15, scale: 0.78, opacity: 0 },
        {
          x: dx * 0.45,
          y: dy * 0.45 - 28,
          z: 65,
          rotationZ: tilt,
          rotationX: 6,
          scale: 1.03,
          opacity: 1,
          duration: 0.18,
          ease: 'power1.out',
        },
      )
      .to(card, {
        x: 0,
        y: 0,
        z: 0,
        rotationX: 0,
        rotationZ: tilt,
        scale: 1.02,
        duration: 0.13,
        ease: 'power2.in',
      })
      .call(() => this.sound('deal'))
      .to(card, { scale: 1, duration: 0.065, ease: 'power1.out' })
    await playTimeline(timeline, signal)
  }
  async flip(id: string, signal: AbortSignal) {
    const inner = this.card(id)?.querySelector<HTMLElement>('.card-inner')
    if (inner)
      await Promise.all(
        [...inner.querySelectorAll('img')].map((image) => image.decode().catch(() => undefined)),
      )
    if (signal.aborted) throw new DOMException('Animation cancelled', 'AbortError')
    if (!inner || this.reduced()) {
      this.sound('flip')
      return
    }
    const timeline = gsap
      .timeline({ paused: true })
      .fromTo(inner, { rotationY: 180 }, { rotationY: 90, duration: 0.2, ease: 'power1.in' })
      .call(() => this.sound('flip'))
      .to(inner, { rotationY: 0, duration: 0.2, ease: 'power1.out' })
    await playTimeline(timeline, signal)
    gsap.set(inner, { clearProps: 'transform' })
  }
  async split(positions: Map<string, DOMRect>, signal: AbortSignal) {
    if (this.reduced()) return
    const timeline = gsap.timeline({ paused: true })
    positions.forEach((from, id) => {
      const card = this.card(id)
      if (!card || !card.getClientRects().length) return
      const to = card.getBoundingClientRect()
      timeline.fromTo(
        card,
        { x: from.left - to.left, y: from.top - to.top, rotationZ: 0 },
        {
          x: 0,
          y: 0,
          rotationZ: id.includes('-h1-') ? 2 : -2,
          duration: 0.32,
          ease: 'power2.inOut',
        },
        0,
      )
    })
    if (timeline.duration()) await playTimeline(timeline, signal)
  }
  async collect(signal: AbortSignal) {
    const cards = [
      ...this.root.querySelectorAll<HTMLElement>('.player-seat [data-card-id]'),
      ...this.root.querySelectorAll<HTMLElement>('.dealer-area [data-card-id]'),
    ].filter((card) => card.getClientRects().length)
    const discard = this.root.querySelector<HTMLElement>('[data-discard]')?.getBoundingClientRect()
    if (!cards.length || !discard || this.reduced()) return
    const timeline = gsap.timeline({ paused: true })
    cards.forEach((card, index) => {
      const from = card.getBoundingClientRect()
      timeline.to(
        card,
        {
          x: discard.left - from.left,
          y: discard.top - from.top,
          rotationZ: 18,
          scale: 0.5,
          opacity: 0,
          duration: 0.4,
          ease: 'power2.in',
        },
        Math.min(index * 0.045, 0.38),
      )
    })
    timeline.call(() => this.sound('collect'), [], 0.12)
    await playTimeline(timeline, signal)
  }
  async shuffle(signal: AbortSignal) {
    const deck = this.root.querySelector('[data-deck]')
    this.sound('shuffle')
    if (!deck || this.reduced()) return
    await playTimeline(
      gsap
        .timeline({ paused: true })
        .to(deck, { x: 5, rotationZ: 2, duration: 0.055, yoyo: true, repeat: 3 })
        .to(deck, { x: 0, rotationZ: 0, duration: 0.06 }),
      signal,
    )
  }
  reset() {
    gsap.set(this.root.querySelectorAll('[data-card-id],.card-inner,[data-deck],[data-bet-chip]'), {
      clearProps: 'transform,opacity',
    })
  }
}
