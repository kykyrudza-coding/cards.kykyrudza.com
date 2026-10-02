import type { Ref } from 'vue'
import { gsap } from 'gsap'
import { usePreferencesStore } from '../stores/preferences'
import { gameAudio, type Sound } from '../audio/GameAudio'
import { assets } from '../config/assets'

// Presentation-only motion for the poker table. Rules and state live on the
// server; this only decorates snapshot changes (cards dealt from the deck,
// community cards flipped, chips swept into the pot and pushed to winners).
export function usePokerMotion(root: Ref<HTMLElement | undefined>) {
  const preferences = usePreferencesStore()
  const media = window.matchMedia('(prefers-reduced-motion: reduce)')
  const cardsStill = () => preferences.reducedMotion || media.matches || !preferences.cardAnimations
  const chipsStill = () => preferences.reducedMotion || media.matches || !preferences.chipAnimations

  const sound = (id: Sound) => gameAudio.play(id)

  function center(rect: DOMRect) {
    return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 }
  }

  const deckCenter = () => {
    const deck = root.value?.querySelector('[data-poker-deck]')
    return deck ? center(deck.getBoundingClientRect()) : null
  }

  /** Card flies from the deck to where it was just rendered (TransitionGroup @enter). */
  function dealIn(el: Element, done: () => void) {
    const card = el as HTMLElement
    const from = deckCenter()
    const delay = Number(card.dataset.order ?? 0) * 0.11
    const target = card.getBoundingClientRect()
    if (!from || cardsStill() || !target.width) {
      if (delay === 0) sound('deal')
      done()
      return
    }
    const to = center(target)
    const inner = card.querySelector<HTMLElement>('.card-inner')
    const flip = card.dataset.flip === '1' && inner
    gsap.set(card, { opacity: 0 })
    const timeline = gsap
      .timeline({ delay, onComplete: () => {
        gsap.set(card, { clearProps: 'transform,opacity' })
        if (inner) gsap.set(inner, { clearProps: 'transform' })
        done()
      } })
      .call(() => sound(flip ? 'flip' : 'deal'))
      .fromTo(
        card,
        { x: from.x - to.x, y: from.y - to.y, rotation: -16, scale: 0.6, opacity: 0 },
        { x: 0, y: 0, rotation: 0, scale: 1, opacity: 1, duration: 0.42, ease: 'power2.out' },
      )
    if (flip)
      timeline.fromTo(inner, { rotationY: 180 }, { rotationY: 0, duration: 0.36, ease: 'power1.out' }, '-=0.1')
  }

  /** Face-up reveal in place — used for opponents' cards at a showdown. */
  function reveal(el: Element, done: () => void) {
    const inner = (el as HTMLElement).querySelector<HTMLElement>('.card-inner')
    const delay = Number((el as HTMLElement).dataset.order ?? 0) * 0.14
    if (!inner || cardsStill()) {
      sound('flip')
      done()
      return
    }
    gsap.fromTo(
      inner,
      { rotationY: 180 },
      {
        rotationY: 0,
        duration: 0.5,
        delay,
        ease: 'power2.out',
        onStart: () => sound('flip'),
        onComplete: () => {
          gsap.set(inner, { clearProps: 'transform' })
          done()
        },
      },
    )
  }

  /** TransitionGroup enter hook: chooses the right entrance per card. */
  function enterCard(el: Element, done: () => void) {
    if ((el as HTMLElement).dataset.reveal === '1') reveal(el, done)
    else dealIn(el, done)
  }

  const leaveInstantly = (_el: Element, done: () => void) => done()

  /** Small chip pop for a freshly placed bet (TransitionGroup @enter). */
  function betIn(el: Element, done: () => void) {
    sound('single')
    if (chipsStill()) return done()
    gsap.fromTo(
      el,
      { scale: 0.3, y: -14, opacity: 0 },
      { scale: 1, y: 0, opacity: 1, duration: 0.3, ease: 'back.out(2)', onComplete: done },
    )
  }

  /**
   * Flies a handful of chip images between two elements. Appended to <body>
   * so no table transform can offset them; always cleaned up.
   */
  function flyChips(from: Element | null, to: Element | null, count = 4, chip = 25) {
    if (!from || !to || chipsStill()) return Promise.resolve()
    const start = center(from.getBoundingClientRect())
    const end = center(to.getBoundingClientRect())
    return Promise.all(
      Array.from({ length: count }, (_, i) => {
        const img = document.createElement('img')
        img.src = assets.chip(chip)
        img.alt = ''
        img.className = 'poker-flying-chip'
        document.body.appendChild(img)
        return new Promise<void>((resolve) => {
          gsap.fromTo(
            img,
            { x: start.x, y: start.y, scale: 0.6, opacity: 0, rotation: i * 40 },
            {
              x: end.x + (i - count / 2) * 6,
              y: end.y + (i % 2) * 5,
              scale: 1,
              opacity: 1,
              rotation: i * 90,
              duration: 0.55,
              delay: i * 0.06,
              ease: 'power2.inOut',
              onComplete: () => {
                img.remove()
                resolve()
              },
            },
          )
        })
      }),
    )
  }

  /** Brief pulse for a changed number (pot size). */
  function bump(el: Element | null) {
    if (!el || chipsStill()) return
    gsap.fromTo(el, { scale: 1.22 }, { scale: 1, duration: 0.4, ease: 'back.out(3)' })
  }

  return { enterCard, leaveInstantly, betIn, flyChips, bump, sound }
}
