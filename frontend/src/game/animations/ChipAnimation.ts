import { gsap } from 'gsap'
import { playTimeline } from './AnimationQueue'
export class ChipAnimation {
  private root: HTMLElement
  private reduced: () => boolean
  constructor(root: HTMLElement, reduced: () => boolean) {
    this.root = root
    this.reduced = reduced
  }
  async settle(signal: AbortSignal) {
    if (this.reduced()) return
    const timeline = gsap.timeline({ paused: true })
    this.root.querySelectorAll<HTMLElement>('[data-hand-result]').forEach((hand) => {
      const chip = hand.querySelector<HTMLElement>('[data-bet-chip]')
      if (!chip || !chip.getClientRects().length) return
      const rect = chip.getBoundingClientRect()
      const target = this.root
        .querySelector<HTMLElement>(
          hand.dataset.handResult === 'lose'
            ? '.dealer-area'
            : `[data-player-id="${hand.dataset.playerId}"] .seat-identity`,
        )
        ?.getBoundingClientRect()
      if (!target) return
      timeline.to(
        chip,
        {
          x: target.left + target.width / 2 - rect.left,
          y: target.top - rect.top,
          opacity: 0,
          scale: 0.65,
          duration: 0.75,
          ease: 'power2.inOut',
        },
        0,
      )
    })
    if (timeline.duration()) await playTimeline(timeline, signal)
  }
}
