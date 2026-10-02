import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMatchStore } from '../stores/match'
import { useAuthStore } from '../stores/auth'
import { usePreferencesStore } from '../stores/preferences'
import { gameAudio } from '../audio/GameAudio'
import { GameAnimationController } from '../game/animations/GameAnimationController'
import { CardAnimation } from '../game/animations/CardAnimation'
import { ChipAnimation } from '../game/animations/ChipAnimation'
import type { PresentationPhase } from '../game/animations/types'
import type { BlackjackMatchData } from '../types/match'
export function useGamePresentation(root: Ref<HTMLElement | undefined>) {
  const store = useMatchStore(),
    auth = useAuthStore(),
    preferences = usePreferencesStore()
  const { t } = useI18n()
  const visible = shallowRef<BlackjackMatchData | null>(null),
    phase = ref<PresentationPhase>('idle'),
    autoError = ref('')
  let controller: GameAnimationController | undefined
  let spokenTwenty = ''
  let soundedTurn = ''
  const media = window.matchMedia('(prefers-reduced-motion: reduce)')
  const reduced = () => preferences.reducedMotion || media.matches || !preferences.cardAnimations
  const busy = computed(() => !['playing', 'betting', 'idle'].includes(phase.value))
  watch([phase, visible], () => {
    const snapshot = visible.value
    if (phase.value !== 'playing' || !snapshot || snapshot.game.current_player_id !== auth.user?.id)
      return
    const index = snapshot.game.current_hand_index ?? 0
    const hand = snapshot.game.players.find((player) => player.id === auth.user?.id)?.hands[index]
    const key = `${snapshot.id}:${snapshot.round}:${index}`
    if (soundedTurn !== key) {
      soundedTurn = key
      gameAudio.play('turn')
    }
    if (hand?.score === 20 && spokenTwenty !== key) {
      spokenTwenty = key
      gameAudio.play('twenty')
    }
  })
  function initialize() {
    if (!root.value || controller) return
    controller = new GameAnimationController(
      new CardAnimation(root.value, reduced, (id) => gameAudio.play(id)),
      new ChipAnimation(root.value, () => reduced() || !preferences.chipAnimations),
      reduced,
      {
        render: (snapshot) => {
          visible.value = snapshot
        },
        phase: (value) => {
          phase.value = value
        },
        ready: () => {},
        event: (event, snapshot) => {
          if (event === 'BET_CHANGED') gameAudio.play('bet')
          if (event === 'PLAYER_SPLIT') gameAudio.play('split')
          if (event === 'ROUND_FINISHED') {
            const hands = snapshot.game.players.find((p) => p.id === auth.user?.id)?.hands ?? []
            if (hands.some((h) => h.result === 'win' || h.result === 'blackjack')) {
              gameAudio.play(hands.some((h) => h.result === 'blackjack') ? 'blackjack' : 'win')
              gameAudio.play('youWin')
            } else if (hands.some((h) => h.result === 'lose')) {
              gameAudio.play('lose')
              if (hands.some((h) => h.status === 'bust')) gameAudio.play('bust')
            } else gameAudio.play('push')
          }
        },
        error: () => {
          autoError.value = t('errors.animationInterrupted')
          const snapshot = store.match
          if (snapshot?.game_type === 'blackjack') controller?.snap(snapshot)
        },
      },
    )
    const initial = store.match
    if (initial?.game_type === 'blackjack') controller.accept(initial)
  }
  // This whole presentation/animation pipeline is Blackjack-specific — Durak
  // renders its match snapshot directly, with no animation layer (yet).
  watch(
    () => store.match,
    (snapshot) => {
      initialize()
      if (snapshot?.game_type === 'blackjack') controller?.accept(snapshot)
    },
    { flush: 'post' },
  )
  function snap() {
    gameAudio.stop()
    const snapshot = store.match
    if (snapshot?.game_type === 'blackjack') controller?.snap(snapshot)
  }
  watch(
    () => [preferences.reducedMotion, preferences.cardAnimations, preferences.chipAnimations],
    snap,
  )
  function visibility() {
    snap()
    if (!document.hidden && store.match)
      void store
        .fetchMatch(store.match.id)
        .then(snap)
        .catch(() => undefined)
  }
  onMounted(() => {
    initialize()
    void gameAudio.preload()
    window.addEventListener('resize', snap)
    document.addEventListener('visibilitychange', visibility)
    media.addEventListener('change', snap)
  })
  onBeforeUnmount(() => {
    controller?.dispose()
    gameAudio.stop()
    window.removeEventListener('resize', snap)
    document.removeEventListener('visibilitychange', visibility)
    media.removeEventListener('change', snap)
  })
  return { visible, phase, busy, autoError, snap }
}
