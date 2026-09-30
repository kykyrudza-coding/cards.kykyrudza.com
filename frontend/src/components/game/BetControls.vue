<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { BlackjackMatchData } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import AppButton from '../ui/AppButton.vue'
const props = defineProps<{
  match: BlackjackMatchData
  viewerId?: number
  busy: boolean
  error?: string | null
}>()
const emit = defineEmits<{ confirm: [amount: number] }>()
const { t } = useI18n()
const player = computed(() => props.match.game.players.find((p) => p.id === props.viewerId))
const balance = computed(() => player.value?.chips ?? 0)
const confirmed = computed(() => props.match.confirmed_bets?.[String(props.viewerId)])
const eligible = computed(() =>
  props.match.game.players.filter((p) => p.status === 'active' && p.chips >= 100),
)
const waiting = computed(() =>
  eligible.value
    .filter((p) => props.match.confirmed_bets?.[String(p.id)] === undefined)
    .map((p) => p.username ?? t('common.player')),
)
function tierStep(value: number): number {
  return value <= 1000 ? 100 : value <= 10000 ? 500 : 1000
}
const maximum = computed(() => {
  const step = tierStep(balance.value)
  return Math.floor(balance.value / step) * step
})
const amount = ref(100)
// Remembers the amount WE actually confirmed last round — not hands[0].bet,
// which reflects any in-hand Double and would otherwise make the next
// round's suggested bet look like it doubled after a loss.
const lastConfirmedAmount = ref<number | null>(null)
watch(
  [() => props.match.id, () => props.match.round],
  () => {
    const preferred = lastConfirmedAmount.value ?? props.match.default_bet ?? 100
    const step = tierStep(preferred)
    amount.value = Math.max(100, Math.min(maximum.value, Math.floor(preferred / step) * step))
  },
  { immediate: true },
)
const locked = computed(
  () =>
    props.busy ||
    confirmed.value !== undefined ||
    player.value?.status !== 'active' ||
    balance.value < 100,
)
function step(direction: number) {
  const increment = direction < 0 ? tierStep(amount.value) : tierStep(amount.value + 1)
  amount.value = Math.max(100, Math.min(maximum.value, amount.value + direction * increment))
}
function confirm() {
  lastConfirmedAmount.value = amount.value
  emit('confirm', amount.value)
}
function onAmountInput(event: Event) {
  const raw = Number((event.target as HTMLInputElement).value)
  if (!Number.isFinite(raw) || raw <= 0) {
    ;(event.target as HTMLInputElement).value = String(amount.value)
    return
  }
  const clamped = Math.max(100, Math.min(maximum.value, raw))
  const step = tierStep(clamped)
  amount.value = Math.round(clamped / step) * step
}
</script>
<template>
  <footer class="bet-controls">
    <div class="bet-heading">
      <span class="eyebrow">{{ t('match.bet.nextHand') }}</span
      ><strong>{{
        confirmed !== undefined
          ? t('match.bet.confirmedAmount', { amount: formatChips(confirmed) })
          : t('match.bet.confirmYourBet')
      }}</strong>
      <p v-if="balance < 100 || player?.status !== 'active'">
        {{ t('match.bet.notEnough') }}
      </p>
      <p v-else>
        {{
          confirmed !== undefined
            ? t('match.bet.waitingFor', {
                names: waiting.join(', ') || t('match.bet.waitingForDeal'),
              })
            : t('match.bet.yourBalance', { amount: formatChips(balance) })
        }}
      </p>
    </div>
    <template v-if="confirmed === undefined && balance >= 100 && player?.status === 'active'"
      ><div class="bet-stepper">
        <AppButton
          :aria-label="t('match.bet.decrease')"
          :disabled="locked || amount <= 100"
          @click="step(-1)"
          >−</AppButton
        ><input
          type="number"
          inputmode="numeric"
          class="bet-amount-input"
          :aria-label="t('match.bet.selectedAmount')"
          :disabled="locked"
          :min="100"
          :max="maximum"
          :value="amount"
          @change="onAmountInput"
        /><AppButton
          :aria-label="t('match.bet.increase')"
          :disabled="locked || amount >= maximum"
          @click="step(1)"
          >+</AppButton
        >
      </div>
      <AppButton
        variant="primary"
        :loading="busy"
        :disabled="locked"
        @click="confirm"
        >{{ t('match.bet.confirm') }}</AppButton
      ></template
    ><small
      >{{ t('match.bet.stepHint') }}<br />{{
        t('match.bet.readyCount', {
          ready: eligible.length - waiting.length,
          total: eligible.length,
        })
      }}</small
    >
    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </footer>
</template>
