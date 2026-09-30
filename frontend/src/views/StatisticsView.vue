<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStatisticsStore } from '../stores/statistics'
import { formatChips } from '../config/gameLabels'
import AppTabs from '../components/ui/AppTabs.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const store = useStatisticsStore()
onMounted(() => store.fetch())

const tab = ref('overview')
const tabs = computed(() => [
  { id: 'overview', label: t('statistics.tabs.overview') },
  { id: 'blackjack', label: t('statistics.tabs.blackjack') },
  { id: 'durak', label: t('statistics.tabs.durak') },
  { id: 'poker', label: t('statistics.tabs.poker'), disabled: true },
])

const winRate = (value: number | null) => (value === null ? '—' : `${value}%`)

const metrics = computed(() => {
  const data = store.data
  if (!data) return []
  if (tab.value === 'blackjack') {
    const s = data.blackjack
    return [
      { label: t('statistics.metrics.played'), value: s.played },
      { label: t('statistics.metrics.wins'), value: s.won },
      { label: t('statistics.metrics.losses'), value: s.losses },
      { label: t('statistics.metrics.winRate'), value: winRate(s.win_rate) },
      { label: t('statistics.metrics.blackjacksHit'), value: s.blackjacks_hit },
      { label: t('statistics.metrics.splits'), value: s.splits_performed },
      { label: t('statistics.metrics.dealerBusts'), value: s.dealer_busts_witnessed },
    ]
  }
  if (tab.value === 'durak') {
    const s = data.durak
    return [
      { label: t('statistics.metrics.played'), value: s.played },
      { label: t('statistics.metrics.survived'), value: s.survived },
      { label: t('statistics.metrics.losses'), value: s.losses },
      { label: t('statistics.metrics.winRate'), value: winRate(s.win_rate) },
    ]
  }
  const s = data.overview
  return [
    { label: t('statistics.metrics.played'), value: s.played },
    { label: t('statistics.metrics.wins'), value: s.won },
    { label: t('statistics.metrics.losses'), value: s.losses },
    { label: t('statistics.metrics.winRate'), value: winRate(s.win_rate) },
    { label: t('statistics.metrics.currentStreak'), value: s.current_win_streak },
    { label: t('statistics.metrics.bestStreak'), value: s.best_win_streak },
    { label: t('statistics.metrics.peakChips'), value: formatChips(s.peak_match_chips) },
  ]
})

const gamesPlayed = computed(() => {
  if (tab.value === 'blackjack') return store.data?.blackjack.played ?? 0
  if (tab.value === 'durak') return store.data?.durak.played ?? 0
  return store.data?.overview.played ?? 0
})

const performanceTitle = computed(() =>
  tab.value === 'blackjack'
    ? t('statistics.performanceBlackjack')
    : tab.value === 'durak'
      ? t('statistics.performanceDurak')
      : t('statistics.performanceOverall'),
)
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('statistics.eyebrow') }}</span>
        <h1>{{ t('statistics.title') }}</h1>
        <p>{{ t('statistics.subtitle') }}</p>
      </div>
    </header>
    <AppTabs v-model="tab" :label="t('statistics.tabsAria')" :tabs="tabs" />
    <div v-if="store.data" class="metric-grid">
      <div v-for="metric in metrics" :key="metric.label" class="panel metric">
        <span>{{ metric.label }}</span
        ><strong>{{ metric.value }}</strong>
      </div>
    </div>
    <section v-if="store.data && gamesPlayed === 0" class="panel">
      <div class="row-between">
        <h2>{{ performanceTitle }}</h2>
      </div>
      <EmptyState
        icon="stats"
        :title="t('statistics.noGamesTitle')"
        :description="t('statistics.noGamesDescription')"
      />
    </section>
  </main>
</template>
