<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import EmptyState from '../components/ui/EmptyState.vue'
import AppBadge from '../components/ui/AppBadge.vue'
const { t } = useI18n()
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('achievements.eyebrow') }}</span>
        <h1>{{ t('achievements.title') }}</h1>
        <p>{{ t('achievements.subtitle') }}</p>
      </div>
      <AppBadge>{{ t('achievements.badge') }}</AppBadge>
    </header>
    <section class="panel">
      <EmptyState
        icon="trophy"
        :title="t('achievements.emptyTitle')"
        :description="t('achievements.emptyDescription')"
      />
    </section>
  </main>
</template>
