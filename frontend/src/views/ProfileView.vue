<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import AppAvatar from '../components/ui/AppAvatar.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppButton from '../components/ui/AppButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const logoutOpen = ref(false)
async function logout() {
  await auth.logout()
  logoutOpen.value = false
  await router.push('/login')
}
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
    <section class="panel profile-header">
      <AppAvatar :name="auth.user?.username ?? t('common.player')" :src="auth.user?.avatar" />
      <div>
        <h2>{{ auth.user?.username ?? t('common.player') }}</h2>
        <p class="muted">{{ auth.user?.email }}</p>
        <span class="small muted">{{ t('profile.memberDetailsSoon') }}</span>
      </div>
    </section>
    <div class="metric-grid">
      <div
        v-for="label in [
          t('profile.metrics.matches'),
          t('profile.metrics.wins'),
          t('profile.metrics.winRate'),
          t('profile.metrics.favouriteGame'),
        ]"
        :key="label"
        class="panel metric"
      >
        <span>{{ label }}</span
        ><strong>—</strong>
      </div>
    </div>
    <section class="panel">
      <EmptyState :title="t('profile.storyTitle')" :description="t('profile.storyDescription')" />
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
