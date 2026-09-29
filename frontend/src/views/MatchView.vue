<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useMatchStore } from '../stores/match'
import { usePreferencesStore } from '../stores/preferences'
import { useConnection } from '../composables/useConnection'
import { useGamePresentation } from '../composables/useGamePresentation'
import type { BlackjackAction } from '../types/match'
import GameStage from '../components/game/GameStage.vue'
import GameHUD from '../components/game/GameHUD.vue'
import AppDrawer from '../components/ui/AppDrawer.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppButton from '../components/ui/AppButton.vue'
import PreferencesPanel from '../components/settings/PreferencesPanel.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const auth = useAuthStore(),
  store = useMatchStore(),
  preferences = usePreferencesStore(),
  route = useRoute(),
  router = useRouter()
const root = ref<HTMLElement>(),
  settingsOpen = ref(false),
  leaveOpen = ref(false),
  finishOpen = ref(false)
const id = computed(() => Number(route.params.id))
const match = computed(() => (store.match?.id === id.value ? store.match : null))
const blocked = computed(() => settingsOpen.value || leaveOpen.value || finishOpen.value)
const presentation = useGamePresentation(root)
const { visible, phase, busy, autoError } = presentation
const { status, monitor } = useConnection(async () => {
  await store.fetchMatch(id.value)
  presentation.snap()
})
async function load() {
  store.disconnectRealtime()
  store.match = null
  if (!Number.isSafeInteger(id.value) || id.value <= 0) {
    store.error = t('match.invalidId')
    return
  }
  try {
    await store.fetchMatch(id.value)
    store.connectRealtime(id.value)
    monitor()
  } catch {
    /* Store exposes error. */
  }
}
watch(id, load, { immediate: true })
async function act(action: BlackjackAction) {
  if (
    !match.value ||
    busy.value ||
    store.actionLoading ||
    match.value.status !== 'active' ||
    match.value.game.current_player_id !== auth.user?.id ||
    !match.value.game.allowed_actions.includes(action)
  )
    return
  try {
    await store[action]()
  } catch {
    /* Store exposes error. */
  }
}
async function finish() {
  try {
    await store.finishMatch()
    finishOpen.value = false
  } catch {
    /* Keep confirmation available for retry. */
  }
}
function requestFinish() {
  settingsOpen.value = false
  finishOpen.value = true
}
function shortcut(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null
  if (
    !preferences.shortcuts ||
    event.repeat ||
    event.ctrlKey ||
    event.metaKey ||
    event.altKey ||
    blocked.value ||
    target?.closest('input,textarea,select,[contenteditable=true],button,a')
  )
    return
  const action = ({ h: 'hit', s: 'stand', d: 'double', p: 'split' } as const)[
    event.key.toLowerCase() as 'h' | 's' | 'd' | 'p'
  ]
  if (action) {
    event.preventDefault()
    void act(action)
  }
}
onMounted(() => window.addEventListener('keydown', shortcut))
onUnmounted(() => {
  store.disconnectRealtime()
  window.removeEventListener('keydown', shortcut)
})
</script>
<template>
  <main ref="root" class="match-screen" :data-presentation-phase="phase">
    <GameStage :match="match ? visible : null" :phase="phase" :viewer-id="auth.user?.id" /><GameHUD
      v-if="match"
      :match="match"
      :viewer-id="auth.user?.id"
      :busy="store.actionLoading || busy"
      :connection="status"
      :lobby-code="match.lobby_code"
      :error="store.error"
      :phase="phase"
      :auto-error="autoError"
      @action="act"
      @settings="settingsOpen = true"
      @leave="leaveOpen = true"
      @refresh="
        store
          .fetchMatch(id)
          .then(presentation.snap)
          .catch(() => undefined)
      "
      @bet="store.placeBet($event).catch(() => undefined)"
    />
    <div v-else class="match-fallback panel">
      <div v-if="store.loading" class="skeleton" :aria-label="t('match.loadingAria')" />
      <EmptyState
        v-else
        :title="t('match.tableUnavailable')"
        :description="store.error ?? t('match.tableUnavailableDescription')"
        ><AppButton @click="load">{{ t('match.tryAgain') }}</AppButton
        ><RouterLink to="/dashboard" class="btn btn-ghost">{{ t('match.backToPlay') }}</RouterLink></EmptyState
      >
    </div>
    <AppDrawer :open="settingsOpen" :title="t('match.settingsTitle')" @close="settingsOpen = false"
      ><PreferencesPanel /><AppButton
        v-if="match?.host_id === auth.user?.id && match?.status === 'active'"
        variant="danger"
        @click="requestFinish"
        >{{ t('match.finishMatch') }}</AppButton
      ></AppDrawer
    ><AppModal :open="leaveOpen" :title="t('match.leaveModal.title')" @close="leaveOpen = false"
      ><p class="muted">
        {{ t('match.leaveModal.description') }}
      </p>
      <div class="modal-actions">
        <AppButton @click="leaveOpen = false">{{ t('match.leaveModal.stay') }}</AppButton
        ><AppButton variant="danger" @click="router.push('/dashboard')">{{
          t('match.leaveModal.leave')
        }}</AppButton>
      </div></AppModal
    ><AppModal :open="finishOpen" :title="t('match.finishModal.title')" @close="finishOpen = false"
      ><p class="muted">{{ t('match.finishModal.description') }}</p>
      <p v-if="store.error" class="error" role="alert">{{ store.error }}</p>
      <div class="modal-actions">
        <AppButton @click="finishOpen = false">{{ t('match.finishModal.keepPlaying') }}</AppButton
        ><AppButton variant="danger" :loading="store.actionLoading" @click="finish"
          >{{ t('match.finishModal.finish') }}</AppButton
        >
      </div></AppModal
    >
  </main>
</template>
