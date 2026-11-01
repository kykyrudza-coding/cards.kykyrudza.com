<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { DurakMatchData, PlayingCard } from '../../types/match'
import { assets } from '../../config/assets'
import { keyOf, same, SUIT_GLYPH } from '../../game/durak/cards'
import { useMatchStore } from '../../stores/match'
import { usePreferencesStore } from '../../stores/preferences'
import { usePokerClock } from '../../composables/usePokerClock'
import DurakPlayerSeat from './DurakPlayerSeat.vue'
import DurakHand from './DurakHand.vue'
import DurakBattlefield from './DurakBattlefield.vue'
import DurakLog from './DurakLog.vue'
import SeatIdentity from '../game/SeatIdentity.vue'
import PlayingCardView from '../game/PlayingCard.vue'
import AppButton from '../ui/AppButton.vue'
import AppAvatar from '../ui/AppAvatar.vue'

const props = defineProps<{ match: DurakMatchData; viewerId?: number }>()
const { t } = useI18n()
const store = useMatchStore()
const preferences = usePreferencesStore()
const router = useRouter()

const game = computed(() => props.match.game)
const own = computed(() => game.value.players.find((p) => p.id === props.viewerId))
const allowed = computed(() => game.value.allowed_actions)
const hints = computed(() => game.value.hints)
const finished = computed(() => game.value.phase === 'finished')
const isDefender = computed(() => game.value.defender_id === props.viewerId)
const outstanding = computed(() => game.value.table.filter((slot) => !slot.defense))
const busy = computed(() => store.actionLoading)
const nameOf = (id: number | null) =>
  game.value.players.find((p) => p.id === id)?.username ?? t('common.player')

// --- seating: everyone in a circle, the viewer at the bottom -----------------
// Opponents are listed clockwise starting from the viewer's left, which is the
// order turns pass in; on wide screens they sit on the arc over the top.
const opponents = computed(() => {
  const ordered = [...game.value.players].sort((a, b) => a.seat - b.seat)
  const at = ordered.findIndex((p) => p.id === props.viewerId)
  const rotated = at < 0 ? ordered : [...ordered.slice(at + 1), ...ordered.slice(0, at)]
  return rotated.filter((p) => p.id !== props.viewerId)
})
function arcStyle(index: number) {
  const n = opponents.value.length
  // A narrow arc for a few players, widening to the sides for a full table.
  const spread = n <= 2 ? 0.62 : n === 3 ? 0.8 : 0.94
  const angle = Math.PI * (1.5 - spread / 2 + ((index + 0.5) / n) * spread)
  return {
    '--sx': `${50 + 41 * Math.cos(angle)}%`,
    '--sy': `${61 + 46 * Math.sin(angle)}%`,
  }
}

// --- who is expected to act ---------------------------------------------------
const actingIds = computed<number[]>(() => {
  if (finished.value) return []
  const g = game.value
  if (g.table.length === 0) return [g.attacker_id]
  if (outstanding.value.length > 0 && !g.taking) return [g.defender_id]
  return g.throwers.filter((id) => !g.passed.includes(id))
})

// --- clock -----------------------------------------------------------------------
const now = usePokerClock(toRef(() => game.value.server_time))
const turnShare = computed(() => {
  const { turn_deadline: deadline, turn_seconds: seconds } = game.value
  if (finished.value || deadline === null || seconds <= 0) return null
  return Math.min(1, Math.max(0, (deadline - now.value) / seconds))
})
const timerFor = (id: number) => (actingIds.value.includes(id) ? turnShare.value : null)
const iAmActing = computed(() => props.viewerId !== undefined && actingIds.value.includes(props.viewerId))

// Once the clock is out anyone seated nudges the server, which re-checks it.
const firedFor = ref('')
const seatRank = computed(() =>
  Math.max(0, game.value.players.findIndex((p) => p.id === props.viewerId)),
)
const expiryKey = computed(() => {
  const g = game.value
  return !finished.value && g.turn_deadline !== null && now.value >= g.turn_deadline + 0.3
    ? `${g.round}:${g.turn_deadline}`
    : ''
})
let expiryTimer: ReturnType<typeof setTimeout> | undefined
watch(expiryKey, (key) => {
  if (!key || key === firedFor.value || !own.value) return
  firedFor.value = key
  expiryTimer = setTimeout(async () => {
    await store.timeout().catch(() => undefined)
    store.error = null // losing the race to another client is not worth showing
  }, seatRank.value * 700)
})

// --- picking cards ----------------------------------------------------------------
const pendingCard = ref<PlayingCard | null>(null) // a card waiting for a slot to beat
const pendingTarget = ref<PlayingCard | null>(null) // a slot waiting for a card to beat it
const translateMode = ref(false)
const dragging = ref<PlayingCard | null>(null)
const flash = ref('')
const shaking = ref('')
let flashTimer: ReturnType<typeof setTimeout> | undefined

function reset() {
  pendingCard.value = null
  pendingTarget.value = null
  translateMode.value = false
  dragging.value = null
}
watch(
  () => `${game.value.round}:${game.value.table.length}:${allowed.value.join(',')}`,
  reset,
)

function say(message: string, card?: PlayingCard) {
  flash.value = message
  if (card) shaking.value = keyOf(card)
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => {
    flash.value = ''
    shaking.value = ''
  }, 2400)
}

const defenseOptions = (card: PlayingCard) =>
  hints.value.defenses.filter((d) => d.with.some((c) => same(c, card)))

const playableKeys = computed(() => {
  const keys = new Set<string>()
  if (finished.value || busy.value) return keys
  if (translateMode.value) {
    hints.value.translate.forEach((c) => keys.add(keyOf(c)))
  } else if (allowed.value.includes('defend')) {
    for (const option of hints.value.defenses) {
      if (pendingTarget.value && !same(option.attack, pendingTarget.value)) continue
      option.with.forEach((c) => keys.add(keyOf(c)))
    }
  } else if (allowed.value.includes('attack')) {
    hints.value.playable.forEach((c) => keys.add(keyOf(c)))
  }
  return keys
})
const handInteractive = computed(
  () => !finished.value && (allowed.value.length > 0 || translateMode.value),
)

/** Attack cards a picked or dragged card can beat. */
const slotTargets = computed(() => {
  const keys = new Set<string>()
  const card = pendingCard.value ?? dragging.value
  if (!card || !allowed.value.includes('defend')) return keys
  defenseOptions(card).forEach((option) => keys.add(keyOf(option.attack)))
  return keys
})

async function run(action: () => Promise<void>) {
  if (busy.value) return
  try {
    await action()
  } catch {
    /* The store shows the server's message. */
  }
  reset()
}
const attack = (cards: PlayingCard[]) => run(() => store.attack(cards))
const defend = (attackCard: PlayingCard, card: PlayingCard) =>
  run(() => store.defend(attackCard, card))
const translate = (cards?: PlayingCard[]) => run(() => store.translate(cards))
const take = () => run(() => store.take())
const pass = () => run(() => store.pass())

function onCard(card: PlayingCard) {
  if (busy.value || finished.value) return

  if (translateMode.value) {
    if (hints.value.translate.some((c) => same(c, card))) void translate([card])
    else say(t('durak.hint.notTranslate'), card)
    return
  }

  if (allowed.value.includes('defend')) {
    const options = defenseOptions(card)
    if (options.length === 0) {
      say(t('durak.hint.noBeat'), card)
      return
    }
    if (pendingTarget.value) {
      const target = options.find((o) => same(o.attack, pendingTarget.value!))
      if (target) void defend(target.attack, card)
      else say(t('durak.hint.noBeat'), card)
      return
    }
    if (options.length === 1) {
      void defend(options[0]!.attack, card)
      return
    }
    // It could beat several cards: wait for the player to say which.
    pendingCard.value = same(pendingCard.value ?? { rank: '', suit: '' }, card) ? null : card
    return
  }

  if (allowed.value.includes('attack')) {
    if (hints.value.playable.some((c) => same(c, card))) void attack([card])
    else say(t('durak.hint.rank'), card)
    return
  }

  say(t('durak.hint.notNow'), card)
}

function onSlot(index: number) {
  const slot = game.value.table[index]
  if (!slot || slot.defense || !allowed.value.includes('defend') || busy.value) return
  const option = hints.value.defenses.find((o) => same(o.attack, slot.attack))
  if (pendingCard.value) {
    const card = pendingCard.value
    if (option?.with.some((c) => same(c, card))) void defend(slot.attack, card)
    else say(t('durak.hint.noBeat'), card)
    return
  }
  if (!option || option.with.length === 0) {
    say(t('durak.hint.nothingBeats'))
    return
  }
  if (option.with.length === 1) {
    void defend(slot.attack, option.with[0]!)
    return
  }
  pendingTarget.value = pendingTarget.value && same(pendingTarget.value, slot.attack) ? null : slot.attack
}

function onDrop(index: number) {
  const card = dragging.value
  dragging.value = null
  const slot = game.value.table[index]
  if (!card || !slot) return
  if (allowed.value.includes('defend')) {
    if (!slot.defense && defenseOptions(card).some((o) => same(o.attack, slot.attack)))
      void defend(slot.attack, card)
    else say(t('durak.hint.noBeat'), card)
  } else onCard(card)
}
function onDropTable() {
  const card = dragging.value
  dragging.value = null
  if (card && allowed.value.includes('attack')) onCard(card)
}

/** Several cards of one rank at once (e.g. a pair to open with, or to throw in). */
const groups = computed(() => {
  if (!allowed.value.includes('attack') || translateMode.value) return []
  const room = game.value.attack_limit - game.value.table.length
  if (room < 2) return []
  const byRank = new Map<string, PlayingCard[]>()
  for (const card of hints.value.playable) byRank.set(card.rank, [...(byRank.get(card.rank) ?? []), card])
  return [...byRank.entries()]
    .filter(([, cards]) => cards.length >= 2)
    .map(([rank, cards]) => ({ rank, cards: cards.slice(0, room) }))
})

function startTranslate() {
  const cards = hints.value.translate
  if (cards.length === 1) void translate([cards[0]!])
  else translateMode.value = !translateMode.value
}

// --- messages ---------------------------------------------------------------------
const statusMessage = computed(() => {
  const g = game.value
  if (flash.value) return flash.value
  if (finished.value) return t('durak.status.finished')
  if (translateMode.value) return t('durak.status.translateHint')
  if (pendingCard.value) return t('durak.status.pickTarget')
  if (pendingTarget.value) return t('durak.status.pickCard')
  if (isDefender.value) {
    if (g.taking) return t('durak.status.youTake')
    return outstanding.value.length > 1
      ? t('durak.status.yourDefendMany', { n: outstanding.value.length })
      : t('durak.status.yourDefend')
  }
  if (allowed.value.includes('attack'))
    return g.table.length === 0 ? t('durak.status.yourAttack') : t('durak.status.yourThrowIn')
  if (allowed.value.includes('pass')) return t('durak.status.yourPass')
  if (g.taking) return t('durak.status.waitTake', { defender: nameOf(g.defender_id) })
  if (g.table.length === 0) return t('durak.status.waitOpen', { attacker: nameOf(g.attacker_id) })
  if (outstanding.value.length > 0) return t('durak.status.waitDefend', { defender: nameOf(g.defender_id) })
  return t('durak.status.waitThrow')
})

const resultMessage = computed(() => {
  if (!finished.value) return null
  if (game.value.loser_id === null) return t('durak.result.draw')
  if (game.value.loser_id === props.viewerId) return t('durak.result.youLost')
  return t('durak.result.someoneLost', { name: nameOf(game.value.loser_id) })
})
const loser = computed(() => game.value.players.find((p) => p.id === game.value.loser_id))

async function playAgain() {
  try {
    const id = await store.rematch()
    await router.push(`/match/${id}`)
  } catch {
    /* The store shows why. */
  }
}
const nextMatchId = computed(() => props.match.next_match_id ?? null)

// --- keyboard ---------------------------------------------------------------------
function shortcut(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null
  if (
    !preferences.shortcuts ||
    event.repeat ||
    event.ctrlKey ||
    event.metaKey ||
    event.altKey ||
    target?.closest('input,textarea,select,[contenteditable=true],button,a')
  )
    return
  const key = event.key.toLowerCase()
  if (key === 'escape') return reset()
  if (key === 't' && allowed.value.includes('take')) {
    event.preventDefault()
    void take()
  } else if ((key === 'p' || key === ' ') && allowed.value.includes('pass')) {
    event.preventDefault()
    void pass()
  } else if (key === 'r' && allowed.value.includes('translate')) {
    event.preventDefault()
    startTranslate()
  }
}
onMounted(() => window.addEventListener('keydown', shortcut))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', shortcut)
  clearTimeout(expiryTimer)
  clearTimeout(flashTimer)
})
</script>
<template>
  <div class="durak-table" :data-many="opponents.length > 3 ? '' : undefined">
    <div class="durak-opponents">
      <DurakPlayerSeat
        v-for="(player, index) in opponents"
        :key="player.id"
        :player="player"
        :is-attacker="player.id === game.attacker_id"
        :is-defender="player.id === game.defender_id"
        :is-thrower="game.throwers.includes(player.id)"
        :passed="game.passed.includes(player.id)"
        :acting="actingIds.includes(player.id)"
        :taking="game.taking"
        :timer="timerFor(player.id)"
        :style="arcStyle(index)"
      />
    </div>

    <div class="durak-center">
      <div class="durak-piles">
        <div class="pile discard-pile-new" :title="t('durak.table.discard')">
          <span class="pile-cards" aria-hidden="true">
            <img v-if="game.discard_count > 0" :src="assets.back(preferences.cardTheme)" alt="" />
          </span>
          <small>{{ t('durak.table.discard') }} <b>{{ game.discard_count }}</b></small>
        </div>
        <div class="pile deck-pile" data-deck :title="t('durak.table.deck')">
          <span class="pile-cards trump-wrap">
            <PlayingCardView
              v-if="game.deck_count > 0"
              :rank="game.trump_card.rank"
              :suit="game.trump_card.suit"
              class="trump-card"
            />
            <img
              v-if="game.deck_count > 1"
              class="deck-top"
              :src="assets.back(preferences.cardTheme)"
              alt=""
            />
            <span v-if="game.deck_count === 0" class="trump-badge-only">{{
              SUIT_GLYPH[game.trump_suit]
            }}</span>
          </span>
          <small>{{ t('durak.table.deck') }} <b>{{ game.deck_count }}</b></small>
        </div>
        <div class="trump-chip" :data-suit="game.trump_suit" :title="t('durak.table.trump')">
          <span>{{ SUIT_GLYPH[game.trump_suit] }}</span>
          <small>{{ t('durak.table.trump') }}</small>
        </div>
      </div>

      <DurakBattlefield
        :table="game.table"
        :can-defend="allowed.includes('defend') && !busy"
        :targets="slotTargets"
        :picked="pendingTarget ? keyOf(pendingTarget) : null"
        :dragging="!!dragging"
        :taking="game.taking"
        @slot="onSlot"
        @drop="onDrop"
        @drop-table="onDropTable"
      />
      <p v-if="game.taking && !finished" class="taking-banner" role="status">
        {{
          isDefender
            ? t('durak.status.youTake')
            : t('durak.status.waitTake', { defender: nameOf(game.defender_id) })
        }}
      </p>
      <p v-if="game.table.length > 0 && !finished" class="limit-chip">
        {{ t('durak.table.limit', { n: game.table.length, max: game.attack_limit }) }}
      </p>
    </div>

    <DurakLog :entries="game.log" :name-of="nameOf" />

    <div v-if="finished" class="durak-result panel" role="status">
      <AppAvatar
        v-if="loser"
        :name="loser.username ?? t('common.player')"
        :src="loser.avatar"
        class="result-avatar"
      />
      <h2>{{ resultMessage }}</h2>
      <div class="result-actions">
        <AppButton
          v-if="nextMatchId"
          variant="primary"
          @click="router.push(`/match/${nextMatchId}`)"
          >{{ t('durak.actions.joinNew') }}</AppButton
        >
        <AppButton v-else variant="primary" :disabled="busy" @click="playAgain">{{
          t('durak.actions.playAgain')
        }}</AppButton>
        <AppButton variant="ghost" @click="router.push('/dashboard')">{{
          t('durak.actions.backToMenu')
        }}</AppButton>
      </div>
      <p v-if="store.error" class="error" role="alert">{{ store.error }}</p>
    </div>

    <div v-if="own" class="durak-own-seat">
      <div v-if="groups.length" class="rank-groups" role="group" :aria-label="t('durak.actions.setsAria')">
        <button
          v-for="group in groups"
          :key="group.rank"
          type="button"
          class="rank-group"
          :disabled="busy"
          @click="attack(group.cards)"
        >
          <span class="rank-group-rank">{{ group.rank }}</span>
          <span>×{{ group.cards.length }}</span>
        </button>
      </div>

      <DurakHand
        :cards="own.hand ?? []"
        :trump-suit="game.trump_suit"
        :playable="playableKeys"
        :interactive="handInteractive"
        :selected="pendingCard"
        :shaking="shaking"
        @play="onCard"
        @dragstart="dragging = $event"
        @dragend="dragging = null"
      />

      <div class="durak-controls" :class="{ acting: iAmActing }">
        <SeatIdentity
          class="own-plate"
          :name="own.username ?? t('common.player')"
          :avatar="own.avatar"
          :you-label="t('match.seat.you')"
          :dot="iAmActing"
        >{{ t('durak.seat.cardCount', { n: own.hand_count }) }}</SeatIdentity>
        <div class="controls-main">
          <span class="durak-status" aria-live="polite" :class="{ flash: !!flash }">{{ statusMessage }}</span>
          <div
            v-if="turnShare !== null"
            class="durak-timer"
            :class="{ low: turnShare < 0.25, mine: iAmActing }"
            aria-hidden="true"
          >
            <span :style="{ width: `${turnShare * 100}%` }" />
          </div>
          <p v-if="store.error && !finished" class="error" role="alert">{{ store.error }}</p>
          <div class="durak-buttons">
            <AppButton
              v-if="allowed.includes('translate')"
              variant="secondary"
              :disabled="busy"
              :aria-pressed="translateMode"
              @click="startTranslate"
              >{{ translateMode ? t('durak.actions.cancel') : t('durak.actions.translate') }}<kbd>R</kbd></AppButton
            ><AppButton
              v-if="allowed.includes('take')"
              variant="secondary"
              :disabled="busy"
              @click="take"
              >{{ t('durak.actions.take') }}<kbd>T</kbd></AppButton
            ><AppButton
              v-if="allowed.includes('pass')"
              variant="primary"
              :disabled="busy"
              @click="pass"
              >{{ t('durak.actions.pass') }}<kbd>P</kbd></AppButton
            ><AppButton
              v-if="pendingCard || pendingTarget"
              variant="ghost"
              @click="reset"
              >{{ t('durak.actions.cancel') }}</AppButton
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
