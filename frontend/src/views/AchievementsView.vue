<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAchievementsStore } from '../stores/achievements'
import AppIcon from '../components/ui/AppIcon.vue'
import AppBadge from '../components/ui/AppBadge.vue'
const { t, locale } = useI18n()
const store = useAchievementsStore()
onMounted(() => store.fetchCatalog())
const unlockedCount = computed(() => store.catalog.filter((a) => a.unlocked_at !== null).length)
function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('achievements.eyebrow') }}</span>
        <h1>{{ t('achievements.title') }}</h1>
        <p>{{ t('achievements.subtitle') }}</p>
      </div>
      <AppBadge v-if="store.catalog.length" tone="success">{{
        t('achievements.progress', { unlocked: unlockedCount, total: store.catalog.length })
      }}</AppBadge>
    </header>
    <div v-if="store.catalog.length" class="collection-grid">
      <article
        v-for="achievement in store.catalog"
        :key="achievement.key"
        class="panel achievement-card"
        :class="{ 'achievement-locked': !achievement.unlocked_at }"
      >
        <span class="achievement-icon"><AppIcon :name="(achievement.icon as never)" :size="26" /></span>
        <h3>{{ t(`achievements.catalog.${achievement.key}.name`) }}</h3>
        <p class="muted small">{{ t(`achievements.catalog.${achievement.key}.description`) }}</p>
        <AppBadge v-if="achievement.unlocked_at" tone="success">{{
          t('achievements.unlockedOn', { date: formatDate(achievement.unlocked_at) })
        }}</AppBadge>
        <AppBadge v-else>{{ t('achievements.lockedLabel') }}</AppBadge>
      </article>
    </div>
  </main>
</template>
