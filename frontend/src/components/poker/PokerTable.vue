<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, toRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { PokerLogEntry, PokerMatchData, PokerPlayerView } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import { assets } from '../../config/assets'
import { useMatchStore } from '../../stores/match'
import { usePreferencesStore } from '../../stores/preferences'
import { usePokerMotion } from '../../composables/usePokerMotion'
import { usePokerClock } from '../../composables/usePokerClock'
import { resultsVisible as isAnnounced, visibleCommunity } from '../../game/poker/timeline'
import { pokerMotionKey } from './motion'
import PokerPlayerSeat from './PokerPlayerSeat.vue'
import PlayingCardView from '../game/PlayingCard.vue'
import ChipPile from '../game/ChipPile.vue'
import AppButton from '../ui/AppButton.vue'
import AppAvatar from '../ui/AppAvatar.vue'

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

// --- clocks ----------------------------------------------------------------
const now = usePokerClock(toRef(() => game.value.server_time))
const sinceEnd = computed(() =>
  game.value.hand_ended_at === null ? Infinity : now.value - game.value.hand_ended_at,
)
/** Community cards on show — an all-in runout is turned over street by street. */
const shownCount = computed(() => visibleCommunity(game.value, sinceEnd.value))
const board = computed(() => game.value.community.slice(0, shownCount.value))
const announced = computed(() => isAnnounced(game.value, sinceEnd.value))
const phaseLabel = computed(() => {
  if (handOver.value && shownCount.value < 5 && shownCount.value >= 3)
    return t(`poker.phase.${shownCount.value === 3 ? 'flop' : shownCount.value === 4 ? 'turn' : 'river'}`)
  if (handOver.value && game.value.showdown && !announced.value) return t('poker.phase.showdown')
  return t(`poker.phase.${game.value.phase}`)
})

const turnShare = computed(() => {
  const { turn_deadline: deadline, turn_seconds: seconds } = game.value
  if (deadline === null || seconds <= 0) return null
  return Math.min(1, Math.max(0, (deadline - now.value) / seconds))
})
const timerFor = (id: number) => (game.value.current_player_id === id ? turnShare.value : null)
const nextHandIn = computed(() =>
  game.value.next_hand_at === null ? 0 : Math.ceil(game.value.next_hand_at + 0.6 - now.value),
)

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

// --- results ---------------------------------------------------------------
const winAmounts = computed(() => {
  const map = new Map<number, number>()
  if (handOver.value && announced.value)
    for (const r of game.value.results) map.set(r.user_id, r.amount)
  return map
})
/** The cards that made the winning hands: they glow, everything else dims. */
const winningKeys = computed(() => {
  const keys = new Set<string>()
  if (announced.value && game.value.showdown)
    for (const r of game.value.results) for (const c of r.cards) keys.add(`${c.rank}|${c.suit}`)
  return keys
})
const isWinning = (card: { rank: string; suit: string }) =>
  winningKeys.value.has(`${card.rank}|${card.suit}`)
const cardClass = (card: { rank: string; suit: string }) => ({
  'poker-card-win': isWinning(card),
  'poker-card-dim': winningKeys.value.size > 0 && !isWinning(card),
})

const resultLines = computed(() => {
  if (!handOver.value || !announced.value) return []
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
  if (game.value.phase !== 'finished' || game.value.winner_id === null || !announced.value)
    return null
  return game.value.winner_id === props.viewerId
    ? t('poker.result.youChamp')
    : t('poker.result.champ', { name: nameOf(game.value.winner_id) })
})

// --- hand history ------------------------------------------------------------
const logOpen = ref(false)
function logLine(entry: PokerLogEntry): string {
  if (entry.action === 'street') return t(`poker.log.street.${entry.phase}`)
  const text = t(`poker.log.${entry.action}`, {
    name: nameOf(entry.user_id),
    n: formatChips(entry.amount),
  })
  return entry.auto ? `${text} ${t('poker.log.timeUp')}` : text
}
const logLines = computed(() =>
  game.value.log.map((entry, index) => ({ key: `${game.value.round}-${index}`, text: logLine(entry) })),
)

// --- raise control ---------------------------------------------------------
const raiseOpen = ref(false)
const raiseTo = ref(game.value.min_raise_to)
// Keyed by primitives: a refetch with an unchanged table must not reset the
// amount the player is dialling in or collapse the raise panel.
watch(
  () =>
    `${game.value.min_raise_to}:${game.value.max_raise_to}:${game.value.round}:${game.value.phase}`,
  () => {
    raiseTo.value = game.value.min_raise_to
  },
)
watch(
  () => `${allowed.value.includes('raise')}:${game.value.phase}:${game.value.round}`,
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

// --- clock expiry: anyone seated nudges the server, which re-checks its own clock ----------
const firedFor = ref('')
const seatRank = computed(() =>
  Math.max(0, game.value.players.findIndex((p) => p.id === props.viewerId)),
)
const expiryKey = computed(() => {
  const g = game.value
  if (streets.includes(g.phase) && g.turn_deadline !== null && now.value >= g.turn_deadline + 0.3)
    return `turn:${g.round}:${g.turn_deadline}`
  if (g.phase === 'hand_finished' && g.next_hand_at !== null && now.value >= g.next_hand_at + 20.5)
    return `idle:${g.round}`
  return ''
})
let expiryTimer: ReturnType<typeof setTimeout> | undefined
watch(expiryKey, (key) => {
  if (!key || key === firedFor.value || !own.value) return
  firedFor.value = key
  // Stagger by seat so usually just one client's request does the work.
  expiryTimer = setTimeout(async () => {
    await store.timeout().catch(() => undefined)
    store.error = null // losing the race to another client is not an error worth showing
  }, 400 + seatRank.value * 700)
}, { immediate: true }) // a page opened on an already-expired clock must nudge too
onBeforeUnmount(() => clearTimeout(expiryTimer))

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
  else if (key === 'n' && has('next_hand') && announced.value && nextHandIn.value <= 0)
    void run(() => store.nextHand())
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
  () => `${game.value.phase}:${game.value.round}`,
  (key, oldKey) => {
    const [phase, round] = key.split(':') as [string, string]
    const [oldPhase, oldRound] = oldKey.split(':') as [string, string]
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
// The pot is pushed to the winners when the result is announced, not when the
// cards go in — so an all-in runout keeps its suspense.
watch(
  announced,
  (shown) => {
    if (!shown || !handOver.value) return
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
  if (phase === 'finished')
    return announced.value ? t('poker.status.done') : t('poker.status.showdown')
  if (phase === 'hand_finished') {
    if (!announced.value) return t('poker.status.showdown')
    return allowed.value.includes('next_hand')
      ? t('poker.status.over')
      : t('poker.status.waitOthers')
  }
  if (own.value?.status === 'folded') return t('poker.status.folded')
  if (own.value?.status === 'all_in') return t('poker.status.allIn')
  if (game.value.current_player_id === props.viewerId) {
    return game.value.to_call > 0
      ? t('poker.status.your', { n: formatChips(game.value.to_call) })
      : t('poker.status.yourFree')
  }
  return t('poker.status.wait', {
    name: game.value.current_player_id ? nameOf(game.value.current_player_id) : '',
  })
})

const isMyTurn = computed(() => game.value.current_player_id === props.viewerId)
const ownLast = computed(() => {
  const p = own.value
  if (!p?.last_action || ['small_blind', 'big_blind', 'fold', 'all_in'].includes(p.last_action))
    return ''
  return t(`poker.last.${p.last_action}`, { n: formatChips(p.last_amount) })
})
const ownHandName = computed(() =>
  own.value?.hand_name && own.value.status !== 'folded' && (!handOver.value || announced.value)
    ? t(`poker.hands.${own.value.hand_name}`)
    : '',
)
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
          :timer="timerFor(player.id)"
          :winning-keys="winningKeys"
          :announced="announced"
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
            ><small :key="phaseLabel">{{ phaseLabel }}</small>
          </div>
          <ChipPile v-if="game.pot > 0" :amount="game.pot" :max-height="4" class="poker-pot-pile" />
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
              v-for="(card, index) in board"
              :key="`${game.round}-${card.rank}${card.suit}`"
              :rank="card.rank"
              :suit="card.suit"
              :class="cardClass(card)"
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
            <RouterLink v-if="game.phase === 'finished'" to="/dashboard" class="btn btn-secondary">{{
              t('poker.actions.backToMenu')
            }}</RouterLink>
          </div>
        </Transition>
        <button
          v-if="logLines.length"
          type="button"
          class="poker-log"
          :class="{ open: logOpen }"
          :aria-expanded="logOpen"
          :aria-label="t('poker.log.title')"
          @click="logOpen = !logOpen"
        >
          <TransitionGroup name="poker-log-line" tag="ol">
            <li v-for="line in logLines" :key="line.key">{{ line.text }}</li>
          </TransitionGroup>
        </button>
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
        :style="isMyTurn && turnShare !== null ? { '--timer': turnShare } : undefined"
        :data-low="isMyTurn && turnShare !== null && turnShare < 0.25 ? '' : undefined"
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
            :class="cardClass(card)"
            :data-order="index * dealt.length + dealOrder(own)"
            data-reveal="0"
          />
        </TransitionGroup>
        <div class="poker-own-info">
          <AppAvatar :name="own.username ?? t('common.player')" :src="own.avatar" />
          <strong>{{ formatChips(own.chips) }}</strong>
          <span class="poker-own-tags">
            <span v-if="own.is_dealer" class="poker-badge">D</span>
            <span v-if="own.is_small_blind" class="poker-badge">SB</span>
            <span v-if="own.is_big_blind" class="poker-badge">BB</span>
            <Transition name="poker-pop">
              <span v-if="ownLast" :key="ownLast" class="poker-last" :data-tone="own.last_action">{{
                ownLast
              }}</span>
            </Transition>
          </span>
          <span v-if="ownHandName" class="poker-handname">{{ ownHandName }}</span>
          <span v-if="own.bet > 0" class="poker-bet-inline" :data-bet-for="own.id"
            ><ChipPile :amount="own.bet" :max-height="4" />{{ formatChips(own.bet) }}</span
          >
          <span
            v-if="(winAmounts.get(own.id) ?? 0) > 0"
            class="poker-win-float"
            aria-hidden="true"
            >+{{ formatChips(winAmounts.get(own.id) ?? 0) }}</span
          >
        </div>
      </div>
      <div class="poker-controls">
        <div
          v-if="isMyTurn && turnShare !== null"
          class="poker-timer poker-timer--own"
          :class="{ low: turnShare < 0.25 }"
          aria-hidden="true"
        >
          <span :style="{ width: `${turnShare * 100}%` }" />
        </div>
        <span v-if="resultLines.length || matchMessage" class="poker-status-result">{{
          [matchMessage, ...resultLines].filter(Boolean).join(' · ')
        }}</span>
        <span class="poker-status-line" aria-live="polite">{{ statusMessage }}</span>
        <p v-if="store.error" class="error" role="alert">{{ store.error }}</p>
        <div v-if="raiseOpen && allowed.includes('raise')" class="poker-raise">
          <div class="poker-raise-presets">
            <button type="button" @click="setRaiseFraction(0.5)">½</button>
            <button type="button" @click="setRaiseFraction(0.75)">¾</button>
            <button type="button" @click="setRaiseFraction(1)">{{ t('poker.pot') }}</button>
            <button type="button" @click="raiseTo = game.min_raise_to">
              {{ t('poker.actions.min') }}
            </button>
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
            variant="primary"
            :disabled="store.actionLoading"
            @click="run(() => store.check())"
            >{{ t('poker.actions.check') }}<kbd>C</kbd></AppButton
          ><AppButton
            v-if="allowed.includes('call')"
            variant="primary"
            :disabled="store.actionLoading"
            @click="run(() => store.call())"
            >{{ t('poker.actions.call', { n: formatChips(game.to_call) }) }}<kbd>C</kbd></AppButton
          ><template v-if="allowed.includes('raise')">
            <AppButton
              v-if="!raiseOpen"
              variant="secondary"
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
            v-if="allowed.includes('next_hand') && announced"
            variant="primary"
            :disabled="store.actionLoading || nextHandIn > 0"
            @click="run(() => store.nextHand())"
            >{{ t('poker.actions.next')
            }}<template v-if="nextHandIn > 0"> ({{ nextHandIn }})</template
            ><kbd v-else>N</kbd></AppButton
          >
        </div>
      </div>
    </div>
  </div>
</template>
