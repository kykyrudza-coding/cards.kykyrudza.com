<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PokerMatchData, PokerPlayerView } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import { assets } from '../../config/assets'
import { useMatchStore } from '../../stores/match'
import { usePreferencesStore } from '../../stores/preferences'
import { usePokerMotion } from '../../composables/usePokerMotion'
import { pokerMotionKey } from './motion'
import PokerPlayerSeat from './PokerPlayerSeat.vue'
import PlayingCardView from '../game/PlayingCard.vue'
import AppButton from '../ui/AppButton.vue'

const props = defineProps<{ match: PokerMatchData; viewerId?: number; blocked?: boolean }>()
const { t } = useI18n()
const store = useMatchStore()
const preferences = usePreferencesStore()

const root = ref<HTMLElement>()
const potEl = ref<HTMLElement>()
const motion = usePokerMotion(root)
provide(pokerMotionKey, motion)

const game = computed(() => props.match.game)
const own = computed(() => game.value.players.find((p) => p.id === props.viewerId))
const allowed = computed(() => game.value.allowed_actions)
const handOver = computed(() => ['hand_finished', 'finished'].includes(game.value.phase))
const streets = ['preflop', 'flop', 'turn', 'river']
const nameOf = (id: number) =>
  game.value.players.find((p) => p.id === id)?.username ?? t('common.player')

// --- seating ---------------------------------------------------------------
// Opponents in clockwise order starting from the viewer's left; on wide
// screens they're spread over the top arc of the table (CSS flows them into
// rows on small screens, so --sx/--sy are simply ignored there).
const opponents = computed(() => {
  const ordered = [...game.value.players].sort((a, b) => a.seat - b.seat)
  const at = ordered.findIndex((p) => p.id === props.viewerId)
  const rotated = at < 0 ? ordered : [...ordered.slice(at + 1), ...ordered.slice(0, at)]
  return rotated.filter((p) => p.id !== props.viewerId)
})
function arcStyle(index: number) {
  const angle = Math.PI * (1 + (index + 0.5) / opponents.value.length)
  return {
    '--sx': `${50 + 43 * Math.cos(angle)}%`,
    '--sy': `${55 + 36 * Math.sin(angle)}%`,
  }
}
const dealt = computed(() =>
  [...game.value.players].filter((p) => p.status !== 'out').sort((a, b) => a.seat - b.seat),
)
function dealOrder(player: PokerPlayerView) {
  const index = dealt.value.findIndex((p) => p.id === player.id)
  const small = Math.max(0, dealt.value.findIndex((p) => p.is_small_blind))
  return index < 0 ? 0 : (index - small + dealt.value.length) % dealt.value.length
}

const winAmounts = computed(() => {
  const map = new Map<number, number>()
  if (handOver.value) for (const r of game.value.results) map.set(r.user_id, r.amount)
  return map
})

// --- raise control ---------------------------------------------------------
const raiseOpen = ref(false)
const raiseTo = ref(game.value.min_raise_to)
watch(
  () => [game.value.min_raise_to, game.value.max_raise_to, game.value.round, game.value.phase],
  () => {
    raiseTo.value = game.value.min_raise_to
  },
)
watch(
  () => [allowed.value.includes('raise'), game.value.phase, game.value.round],
  () => {
    raiseOpen.value = false
  },
)
const clampedRaise = computed(() =>
  Math.min(
    Math.max(Math.round(Number(raiseTo.value) || 0), game.value.min_raise_to),
    game.value.max_raise_to,
  ),
)
const raiseStep = computed(() => Math.max(1, Math.floor(game.value.small_blind / 5)))
function setRaiseFraction(fraction: number) {
  const target = game.value.current_bet + Math.round((game.value.pot + game.value.to_call) * fraction)
  raiseTo.value = Math.min(Math.max(target, game.value.min_raise_to), game.value.max_raise_to)
}

async function run(action: () => Promise<void>) {
  if (store.actionLoading) return
  await action().catch(() => undefined)
}
const confirmRaise = () => run(() => store.raise(clampedRaise.value))

// --- keyboard shortcuts (F fold · C check/call · R raise · A all-in · N next hand) ----------
function shortcut(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null
  if (
    !preferences.shortcuts ||
    props.blocked ||
    event.repeat ||
    event.ctrlKey ||
    event.metaKey ||
    event.altKey ||
    target?.closest('input,textarea,select,[contenteditable=true],button,a')
  )
    return
  const has = (action: string) => (allowed.value as string[]).includes(action)
  const key = event.key.toLowerCase()
  if (key === 'escape' && raiseOpen.value) raiseOpen.value = false
  else if (key === 'f' && has('fold')) void run(() => store.fold())
  else if (key === 'c' && has('check')) void run(() => store.check())
  else if (key === 'c' && has('call')) void run(() => store.call())
  else if (key === 'a' && has('all_in')) void run(() => store.allIn())
  else if (key === 'n' && has('next_hand')) void run(() => store.nextHand())
  else if (key === 'r' && has('raise')) {
    if (raiseOpen.value) void confirmRaise()
    else raiseOpen.value = true
  } else return
  event.preventDefault()
}
onMounted(() => window.addEventListener('keydown', shortcut))
onBeforeUnmount(() => window.removeEventListener('keydown', shortcut))

// --- motion + sound --------------------------------------------------------
// Runs before the DOM patch so the old bet chips are still there to fly away.
watch(
  () => [game.value.phase, game.value.round] as const,
  ([phase, round], [oldPhase, oldRound]) => {
    if (round !== oldRound) {
      motion.sound('shuffle')
      return
    }
    if (streets.includes(oldPhase) && phase !== oldPhase) {
      root.value
        ?.querySelectorAll('[data-bet-for]')
        .forEach((chip) => void motion.flyChips(chip, potEl.value ?? null, 3))
    }
  },
  { flush: 'pre' },
)
watch(
  () => game.value.pot,
  (pot, old) => {
    if (pot !== old && pot > 0) motion.bump(potEl.value ?? null)
  },
  { flush: 'post' },
)
watch(
  () => game.value.phase,
  (phase, old) => {
    if (!['hand_finished', 'finished'].includes(phase) || !streets.includes(old)) return
    for (const result of game.value.results) {
      const seat =
        result.user_id === props.viewerId
          ? root.value?.querySelector('[data-own-seat]')
          : root.value?.querySelector(`[data-player-id="${result.user_id}"]`)
      void motion.flyChips(potEl.value ?? null, seat ?? null, 5, 100)
    }
    const mine = game.value.results.some((r) => r.user_id === props.viewerId)
    if (mine) {
      motion.sound('win')
      motion.sound('payout')
    } else if (game.value.showdown && ['active', 'all_in'].includes(own.value?.status ?? '')) {
      motion.sound('lose')
    } else motion.sound('collect')
  },
  { flush: 'post' },
)
watch(
  () => game.value.current_player_id,
  (id, old) => {
    if (id === props.viewerId && id !== old) motion.sound('click')
  },
)

// --- messaging -------------------------------------------------------------
const statusMessage = computed(() => {
  const phase = game.value.phase
  if (phase === 'finished') return t('poker.status.done')
  if (phase === 'hand_finished') {
    return allowed.value.includes('next_hand')
      ? t('poker.status.over')
      : t('poker.status.waitOthers')
  }
  if (own.value?.status === 'folded') return t('poker.status.folded')
  if (game.value.current_player_id === props.viewerId) {
    return game.value.to_call > 0
      ? t('poker.status.your', { n: formatChips(game.value.to_call) })
      : t('poker.status.yourFree')
  }
  return t('poker.status.wait', {
    name: game.value.current_player_id ? nameOf(game.value.current_player_id) : '',
  })
})

const resultLines = computed(() => {
  if (!handOver.value) return []
  return game.value.results.map((result) =>
    result.hand
      ? t('poker.result.winWith', {
          name: nameOf(result.user_id),
          n: formatChips(result.amount),
          hand: t(`poker.hands.${result.hand}`),
        })
      : t('poker.result.win', { name: nameOf(result.user_id), n: formatChips(result.amount) }),
  )
})

const matchMessage = computed(() => {
  if (game.value.phase !== 'finished' || game.value.winner_id === null) return null
  return game.value.winner_id === props.viewerId
    ? t('poker.result.youChamp')
    : t('poker.result.champ', { name: nameOf(game.value.winner_id) })
})

const isMyTurn = computed(() => game.value.current_player_id === props.viewerId)
</script>
<template>
  <div ref="root" class="poker-table" :data-count="opponents.length">
    <div class="poker-field">
      <div class="poker-opponents">
        <PokerPlayerSeat
          v-for="(player, index) in opponents"
          :key="player.id"
          :player="player"
          :round="game.round"
          :deal-order="dealOrder(player)"
          :seat-count="dealt.length"
          :is-current="player.id === game.current_player_id"
          :win-amount="winAmounts.get(player.id) ?? 0"
          :style="arcStyle(index)"
        />
      </div>
      <div
        class="poker-center"
        :class="{ 'poker-center--result': resultLines.length > 0 || matchMessage }"
      >
        <div class="poker-info">
          <div class="poker-deck" data-poker-deck aria-hidden="true">
            <img v-for="i in 3" :key="i" :src="assets.back(preferences.cardTheme)" alt="" />
          </div>
          <div ref="potEl" class="poker-pot" aria-live="polite">
            <span>{{ t('poker.pot') }}</span
            ><strong>{{ formatChips(game.pot) }}</strong
            ><small>{{ t(`poker.phase.${game.phase}`) }}</small>
          </div>
        </div>
        <div class="poker-board">
          <div class="poker-board-slots" aria-hidden="true">
            <span v-for="i in 5" :key="i" class="poker-card-slot" />
          </div>
          <TransitionGroup
            tag="div"
            class="poker-community"
            :css="false"
            @enter="motion.enterCard"
            @leave="motion.leaveInstantly"
          >
            <PlayingCardView
              v-for="(card, index) in game.community"
              :key="`${game.round}-${card.rank}${card.suit}`"
              :rank="card.rank"
              :suit="card.suit"
              :style="{ gridColumn: index + 1 }"
              :data-order="index < 3 ? index : 0"
              data-flip="1"
            />
          </TransitionGroup>
        </div>
        <Transition name="poker-fade">
          <div v-if="resultLines.length || matchMessage" class="poker-result panel">
            <p v-if="matchMessage" class="poker-champion">{{ matchMessage }}</p>
            <p v-for="line in resultLines" :key="line">{{ line }}</p>
          </div>
        </Transition>
      </div>
    </div>
    <div v-if="own" class="poker-own-seat" data-own-seat>
      <div
        class="poker-own-hand"
        :class="{
          'poker-folded': own.status === 'folded' || own.status === 'out',
          'poker-winner': (winAmounts.get(own.id) ?? 0) > 0,
          'poker-turn': isMyTurn,
        }"
      >
        <TransitionGroup
          tag="div"
          class="poker-own-cards"
          :css="false"
          @enter="motion.enterCard"
          @leave="motion.leaveInstantly"
        >
          <PlayingCardView
            v-for="(card, index) in own.hand ?? []"
            :key="`${game.round}-${index}`"
            :rank="card.rank"
            :suit="card.suit"
            :data-order="index * dealt.length + dealOrder(own)"
            data-reveal="0"
          />
        </TransitionGroup>
        <div class="poker-own-info">
          <strong>{{ formatChips(own.chips) }}</strong>
          <span class="poker-own-tags">
            <span v-if="own.is_dealer" class="poker-badge">D</span>
            <span v-if="own.is_small_blind" class="poker-badge">SB</span>
            <span v-if="own.is_big_blind" class="poker-badge">BB</span>
          </span>
          <span v-if="own.bet > 0" class="poker-bet-inline" :data-bet-for="own.id">{{
            formatChips(own.bet)
          }}</span>
          <span v-if="(winAmounts.get(own.id) ?? 0) > 0" class="poker-win-float" aria-hidden="true"
            >+{{ formatChips(winAmounts.get(own.id) ?? 0) }}</span
          >
        </div>
      </div>
      <div class="poker-controls">
        <span class="poker-status-line" aria-live="polite">{{ statusMessage }}</span>
        <p v-if="store.error" class="error" role="alert">{{ store.error }}</p>
        <div v-if="raiseOpen && allowed.includes('raise')" class="poker-raise">
          <div class="poker-raise-presets">
            <button type="button" @click="setRaiseFraction(0.5)">½</button>
            <button type="button" @click="setRaiseFraction(0.75)">¾</button>
            <button type="button" @click="setRaiseFraction(1)">{{ t('poker.pot') }}</button>
            <button type="button" @click="raiseTo = game.min_raise_to">Min</button>
          </div>
          <div class="poker-raise-amount">
            <input
              v-model.number="raiseTo"
              type="range"
              :min="game.min_raise_to"
              :max="game.max_raise_to"
              :step="raiseStep"
              :aria-label="t('poker.actions.amount')"
            />
            <input
              v-model.number="raiseTo"
              class="poker-raise-input"
              type="number"
              inputmode="numeric"
              :min="game.min_raise_to"
              :max="game.max_raise_to"
              :aria-label="t('poker.actions.amount')"
            />
          </div>
        </div>
        <div class="poker-buttons">
          <AppButton
            v-if="allowed.includes('fold')"
            variant="ghost"
            :disabled="store.actionLoading"
            @click="run(() => store.fold())"
            >{{ t('poker.actions.fold') }}<kbd>F</kbd></AppButton
          ><AppButton
            v-if="allowed.includes('check')"
            variant="secondary"
            :disabled="store.actionLoading"
            @click="run(() => store.check())"
            >{{ t('poker.actions.check') }}<kbd>C</kbd></AppButton
          ><AppButton
            v-if="allowed.includes('call')"
            variant="secondary"
            :disabled="store.actionLoading"
            @click="run(() => store.call())"
            >{{ t('poker.actions.call', { n: formatChips(game.to_call) }) }}<kbd>C</kbd></AppButton
          ><template v-if="allowed.includes('raise')">
            <AppButton
              v-if="!raiseOpen"
              variant="primary"
              :disabled="store.actionLoading"
              @click="raiseOpen = true"
              >{{ t(game.current_bet > 0 ? 'poker.actions.raiseOpen' : 'poker.actions.betOpen')
              }}<kbd>R</kbd></AppButton
            ><AppButton
              v-else
              variant="primary"
              :disabled="store.actionLoading"
              @click="confirmRaise"
              >{{
                t(game.current_bet > 0 ? 'poker.actions.raise' : 'poker.actions.bet', {
                  n: formatChips(clampedRaise),
                })
              }}<kbd>R</kbd></AppButton
            ><AppButton
              variant="danger"
              :disabled="store.actionLoading"
              @click="run(() => store.allIn())"
              >{{ t('poker.actions.allIn', { n: formatChips(own.chips) }) }}<kbd>A</kbd></AppButton
            >
          </template>
          <AppButton
            v-if="allowed.includes('next_hand')"
            variant="primary"
            :disabled="store.actionLoading"
            @click="run(() => store.nextHand())"
            >{{ t('poker.actions.next') }}<kbd>N</kbd></AppButton
          >
        </div>
      </div>
    </div>
  </div>
</template>
