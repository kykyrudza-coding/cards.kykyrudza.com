<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { gameAudio } from './audio/GameAudio'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { usePreferencesStore } from './stores/preferences'
import AppIcon from './components/ui/AppIcon.vue'
import AppAvatar from './components/ui/AppAvatar.vue'
import AppButton from './components/ui/AppButton.vue'
import AppModal from './components/ui/AppModal.vue'
import AppToast from './components/ui/AppToast.vue'
import LocaleSwitcher from './components/ui/LocaleSwitcher.vue'
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const preferences = usePreferencesStore()
const logoutOpen = ref(false)
const gameRoute = computed(() => route.name === 'match')
const appRoute = computed(() => route.meta.requiresAuth && !gameRoute.value)
const nav = computed(
  () =>
    [
      { path: '/dashboard', label: t('nav.play'), icon: 'play' },
      { path: '/profile', label: t('nav.profile'), icon: 'user' },
      { path: '/statistics', label: t('nav.statistics'), icon: 'stats' },
      { path: '/achievements', label: t('nav.achievements'), icon: 'trophy' },
      { path: '/collection', label: t('nav.collection'), icon: 'collection' },
      { path: '/settings', label: t('nav.settings'), icon: 'settings' },
    ] as const,
)
const mobileNav = computed(() => [nav.value[0], nav.value[2], nav.value[4], nav.value[1]])
watch(
  () => auth.isAuthenticated,
  (authenticated) => {
    if (!authenticated && route.meta.requiresAuth)
      void router.push({ path: '/login', query: { redirect: route.fullPath } })
  },
)
watch(
  preferences.$state,
  (value) => {
    gameAudio.configure(value)
    document.documentElement.dataset.reducedMotion = String(value.reducedMotion)
    document.documentElement.dataset.cardAnimations = String(value.cardAnimations)
    document.documentElement.dataset.chipAnimations = String(value.chipAnimations)
    try {
      localStorage.setItem('poker.preferences', JSON.stringify(value))
    } catch {
      /* Preferences still work in memory. */
    }
  },
  { deep: true, immediate: true },
)
async function logout() {
  await auth.logout()
  logoutOpen.value = false
  await router.push('/login')
}
function unlockAudio(event: Event) {
  void gameAudio.unlock().then(() => {
    if (
      event.type === 'pointerdown' &&
      (event.target as HTMLElement)?.closest('button:not(:disabled)')
    )
      gameAudio.play('click')
  })
}
onMounted(() => {
  document.addEventListener('pointerdown', unlockAudio)
  document.addEventListener('keydown', unlockAudio)
})
onUnmounted(() => {
  document.removeEventListener('pointerdown', unlockAudio)
  document.removeEventListener('keydown', unlockAudio)
})
</script>
<template>
  <a class="skip-link" href="#main-content">{{ t('common.skipToContent') }}</a>
  <div :class="{ 'app-layout': appRoute }">
    <aside v-if="appRoute" class="sidebar">
      <RouterLink to="/dashboard" class="brand"
        ><span class="brand-symbol"><AppIcon name="cards" :size="27" /></span
        ><span>kykyrudza<span class="brand-sub">{{ t('nav.brandSub') }}</span></span></RouterLink
      >
      <span class="nav-label">{{ t('nav.yourNextHand') }}</span>
      <nav :aria-label="t('nav.ariaMain')">
        <RouterLink
          v-for="item in nav"
          :key="item.path"
          :to="item.path"
          class="nav-link"
          :class="{ 'play-link': item.path === '/dashboard' }"
          ><AppIcon :name="item.icon" />{{ item.label
          }}<AppIcon v-if="item.path === '/dashboard'" name="chevron" class="push-right" :size="16"
        /></RouterLink>
      </nav>
      <div class="sidebar-foot">
        <LocaleSwitcher />
        <div class="sidebar-user">
          <AppAvatar
            :name="auth.user?.username ?? t('common.player')"
            :src="auth.user?.avatar"
          /><RouterLink to="/profile"
            ><strong>{{ auth.user?.username ?? t('common.player') }}</strong
            ><small>{{ t('nav.personalProfile') }}</small></RouterLink
          ><button
            type="button"
            class="icon-button"
            :aria-label="t('nav.logOut')"
            @click="logoutOpen = true"
          >
            <AppIcon name="logout" />
          </button>
        </div>
      </div>
    </aside>
    <header v-if="!gameRoute" class="topbar" :class="{ 'public-topbar': !appRoute }">
      <RouterLink :to="appRoute ? '/dashboard' : '/'" class="brand mobile-brand"
        ><AppIcon name="cards" :size="26" /><span>kykyrudza</span></RouterLink
      ><span v-if="appRoute" class="desktop-breadcrumb"
        >{{ t('nav.yourSpace') }} <span>/</span>
        {{ route.meta.title ? t(route.meta.title as string) : t('nav.play') }}</span
      >
      <div class="topbar-right">
        <template v-if="auth.isAuthenticated"
          ><RouterLink to="/settings" class="icon-button" :aria-label="t('nav.settings')"
            ><AppIcon name="settings" /></RouterLink
          ><RouterLink to="/profile" :aria-label="t('profile.title')"
            ><AppAvatar :name="auth.user?.username ?? t('common.player')" /></RouterLink></template
        ><template v-else
          ><RouterLink to="/login" class="text-link">{{ t('nav.signIn') }}</RouterLink
          ><RouterLink to="/register" class="btn btn-secondary"
            >{{ t('nav.createAccount') }} <AppIcon name="arrow" :size="16" /></RouterLink
        ></template>
      </div>
    </header>
    <div id="main-content" tabindex="-1" :class="{ 'app-content': appRoute }"><RouterView /></div>
    <nav v-if="appRoute" class="bottom-nav" :aria-label="t('nav.ariaMobile')">
      <RouterLink v-for="item in mobileNav" :key="item.path" :to="item.path"
        ><AppIcon :name="item.icon" /><span>{{
          item.path === '/statistics' ? t('nav.statsShort') : item.label
        }}</span></RouterLink
      >
    </nav>
  </div>
  <AppModal :open="logoutOpen" :title="t('logoutModal.title')" @close="logoutOpen = false"
    ><p class="muted">{{ t('logoutModal.description') }}</p>
    <div class="modal-actions">
      <AppButton @click="logoutOpen = false">{{ t('common.stayHere') }}</AppButton
      ><AppButton variant="danger" :loading="auth.loading" @click="logout">{{
        t('nav.logOut')
      }}</AppButton>
    </div></AppModal
  >
  <AppToast />
</template>
