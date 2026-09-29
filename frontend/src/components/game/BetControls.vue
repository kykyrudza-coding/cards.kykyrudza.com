<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MatchData } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import AppButton from '../ui/AppButton.vue'
const props = defineProps<{
  match: MatchData
  viewerId?: number
  busy: boolean
  error?: string | null
}>()
defineEmits<{ confirm: [amount: number] }>()
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
const maximum = computed(() =>
  balance.value < 1000
    ? Math.floor(balance.value / 100) * 100
    : Math.floor(balance.value / 500) * 500,
)
const amount = ref(100)
watch(
  [() => props.match.id, () => props.match.round],
  () => {
    const preferred = player.value?.hands[0]?.bet ?? props.match.default_bet ?? 100
    amount.value = Math.max(
      100,
      Math.min(
        maximum.value,
        preferred <= 1000 ? Math.floor(preferred / 100) * 100 : Math.floor(preferred / 500) * 500,
      ),
    )
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
  const increment =
    direction < 0 ? (amount.value <= 1000 ? 100 : 500) : amount.value < 1000 ? 100 : 500
  amount.value = Math.max(100, Math.min(maximum.value, amount.value + direction * increment))
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
        ><output :aria-label="t('match.bet.selectedAmount')">{{ formatChips(amount) }}</output
        ><AppButton
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
        @click="$emit('confirm', amount)"
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
