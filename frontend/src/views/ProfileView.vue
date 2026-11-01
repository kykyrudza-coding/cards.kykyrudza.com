<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useStatisticsStore } from '../stores/statistics'
import AppAvatar from '../components/ui/AppAvatar.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppButton from '../components/ui/AppButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const auth = useAuthStore()
const stats = useStatisticsStore()
const router = useRouter()
const logoutOpen = ref(false)
onMounted(() => stats.fetch())
async function logout() {
  await auth.logout()
  logoutOpen.value = false
  await router.push('/login')
}
const winRate = computed(() => {
  const rate = stats.data?.overview.win_rate
  return rate === null || rate === undefined ? t('profile.metrics.none') : `${rate}%`
})
const favouriteGame = computed(() => {
  if (!stats.data) return t('profile.metrics.none')
  const best = games.value.reduce((a, b) => (b.played > a.played ? b : a))
  return best.played === 0 ? t('profile.metrics.none') : best.label
})
const games = computed(() => {
  const data = stats.data
  return [
    { key: 'blackjack', label: t('lobby.blackjack'), played: data?.blackjack.played ?? 0 },
    { key: 'durak', label: t('lobby.durak'), played: data?.durak.played ?? 0 },
    { key: 'poker', label: t('lobby.poker'), played: data?.poker?.played ?? 0 },
  ]
})
const maxPlayed = computed(() => Math.max(1, ...games.value.map((g) => g.played)))
const rate = computed(() => stats.data?.overview.win_rate ?? 0)
const metrics = computed(() => [
  {
    icon: 'cards',
    label: t('profile.metrics.matches'),
    value: stats.data?.overview.played ?? t('profile.metrics.none'),
  },
  {
    icon: 'trophy',
    label: t('profile.metrics.wins'),
    value: stats.data?.overview.won ?? t('profile.metrics.none'),
  },
  { icon: 'target', label: t('profile.metrics.winRate'), value: winRate.value },
  { icon: 'star', label: t('profile.metrics.favouriteGame'), value: favouriteGame.value },
])
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('profile.eyebrow') }}</span>
        <h1>{{ t('profile.title') }}</h1>
      </div>
      <RouterLink to="/settings" class="btn btn-secondary"
        ><AppIcon name="settings" :size="18" />{{ t('profile.settings') }}</RouterLink
      >
    </header>
    <section class="panel profile-header profile-banner">
      <AppAvatar :name="auth.user?.username ?? t('common.player')" :src="auth.user?.avatar" />
      <div>
        <h2>{{ auth.user?.username ?? t('common.player') }}</h2>
        <p class="muted">{{ auth.user?.email }}</p>
      </div>
    </section>
    <div class="metric-grid">
      <div v-for="metric in metrics" :key="metric.label" class="panel metric">
        <span class="metric-label"
          ><AppIcon :name="(metric.icon as never)" :size="16" />{{ metric.label }}</span
        ><strong>{{ metric.value }}</strong>
      </div>
    </div>
    <section v-if="stats.data && stats.data.overview.played > 0" class="panel performance">
      <div class="win-ring" :style="{ '--rate': rate }" role="img" :aria-label="winRate">
        <strong>{{ winRate }}</strong>
        <small>{{ t('profile.metrics.winRate') }}</small>
      </div>
      <ul class="game-bars">
        <li v-for="game in games" :key="game.key">
          <span class="bar-label">{{ game.label }}</span>
          <span class="bar-track"
            ><span :style="{ width: `${(game.played / maxPlayed) * 100}%` }"
          /></span>
          <span class="bar-value">{{ game.played }}</span>
        </li>
      </ul>
    </section>
    <section class="panel">
      <EmptyState
        v-if="!stats.data || stats.data.overview.played === 0"
        :title="t('profile.storyTitle')"
        :description="t('profile.storyDescription')"
      />
      <div class="button-row">
        <RouterLink to="/achievements" class="btn btn-secondary"
          ><AppIcon name="trophy" />{{ t('profile.achievements') }}</RouterLink
        ><AppButton variant="ghost" @click="logoutOpen = true"
          ><AppIcon name="logout" />{{ t('profile.logout') }}</AppButton
        >
      </div>
    </section>
    <AppModal :open="logoutOpen" :title="t('logoutModal.title')" @close="logoutOpen = false"
      ><p class="muted">{{ t('profile.logoutModal.description') }}</p>
      <div class="modal-actions">
        <AppButton @click="logoutOpen = false">{{ t('profile.logoutModal.stay') }}</AppButton
        ><AppButton variant="danger" :loading="auth.loading" @click="logout">{{
          t('profile.logout')
        }}</AppButton>
      </div></AppModal
    >
  </main>
</template>
