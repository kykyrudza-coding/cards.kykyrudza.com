<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useLobbyStore } from '../stores/lobby'
import { useUiStore } from '../stores/ui'
import { useConnection } from '../composables/useConnection'
import { assets } from '../config/assets'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LobbyCode from '../components/lobby/LobbyCode.vue'
import LobbyPlayerCard from '../components/lobby/LobbyPlayerCard.vue'
import LobbySettings from '../components/lobby/LobbySettings.vue'
const { t } = useI18n()
const auth = useAuthStore()
const lobby = useLobbyStore()
const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const code = computed(() => String(route.params.code).trim().toUpperCase())
const room = computed(() => (lobby.currentLobby?.code === code.value ? lobby.currentLobby : null))
const me = computed(() => room.value?.players.find((player) => player.id === auth.user?.id))
const allReady = computed(
  () => !!room.value?.players.length && room.value.players.every((player) => player.is_ready),
)
const password = ref('')
const leaveOpen = ref(false)
const { status, monitor } = useConnection(() => lobby.fetchLobby(code.value))
function subscribe() {
  if (me.value) {
    lobby.connectRealtime(code.value)
    monitor()
  }
}
watch(
  code,
  async () => {
    lobby.disconnectRealtime()
    lobby.currentLobby = null
    try {
      await lobby.fetchLobby(code.value)
      subscribe()
    } catch {
      /* Store exposes load error. */
    }
  },
  { immediate: true },
)
watch(me, subscribe)
watch([() => room.value?.match_id, () => me.value?.id], ([id, memberId]) => {
  if (id && memberId && room.value?.status === 'started') {
    ui.toast(t('lobby.toastGameStarted'))
    void router.push(`/match/${id}`)
  }
})
watch(
  () => room.value?.players.map((p) => p.id),
  (ids, previous) => {
    if (ids && previous && ids.length > previous.length) ui.toast(t('lobby.toastPlayerJoined'))
  },
)
onUnmounted(() => lobby.disconnectRealtime())
async function join() {
  try {
    await lobby.joinLobby(code.value, password.value || null)
    subscribe()
  } catch {
    /* Store exposes error. */
  }
}
async function ready() {
  const next = !me.value?.is_ready
  try {
    await lobby.setReady(code.value, next)
    ui.toast(next ? t('lobby.toastReady') : t('lobby.toastNotReady'), 'info')
  } catch {
    /* Store exposes error. */
  }
}
async function start() {
  try {
    const match = await lobby.startLobby(code.value)
    await router.push(`/match/${match.id}`)
  } catch {
    /* Store exposes error. */
  }
}
async function leave() {
  try {
    await lobby.leaveLobby(code.value)
    leaveOpen.value = false
    await router.push('/dashboard')
  } catch {
    /* Dialog remains open for retry. */
  }
}
</script>
<template>
  <main class="page lobby-page">
    <RouterLink to="/dashboard" class="text-link back-link"
      ><AppIcon name="back" :size="16" /> {{ t('lobby.backToPlay') }}</RouterLink
    >
    <div v-if="!room && lobby.loading" class="skeleton" :aria-label="t('lobby.loadingAria')" />
    <section v-else-if="!room" class="panel">
      <EmptyState
        :title="t('lobby.unavailableTitle')"
        :description="lobby.error ?? t('lobby.unavailableDescription')"
        ><AppButton @click="lobby.fetchLobby(code).catch(() => undefined)"
          >{{ t('lobby.tryAgain') }}</AppButton
        ></EmptyState
      >
    </section>
    <template v-else
      ><header class="page-heading">
        <div>
          <span class="eyebrow">{{ t('lobby.eyebrow') }}</span>
          <h1>{{ t('lobby.title') }}</h1>
          <LobbyCode :code="code" />
        </div>
        <span v-if="me" class="connection" :class="{ offline: status !== 'Connected' }"
          ><AppIcon :name="status === 'Connected' ? 'connected' : 'disconnected'" :size="16" />{{
            t(`common.connection.${status}`)
          }}</span
        >
      </header>
      <div class="lobby-banner">
        <div>
          <AppBadge tone="success">{{
            room.status === 'waiting'
              ? t('lobby.waitingRoom')
              : room.status === 'closed'
                ? t('lobby.statusClosed')
                : t('lobby.statusStarted')
          }}</AppBadge>
          <h2>{{ t(room.game_type === 'durak' ? 'lobby.durak' : 'lobby.blackjack') }}</h2>
          <p>{{ t('lobby.subtitle') }}</p>
        </div>
        <img :src="assets.back()" :alt="t('lobby.cardBackAlt')" />
      </div>
      <div class="lobby-columns">
        <section class="panel">
          <div class="row-between">
            <h2>{{ t('lobby.atTable') }}</h2>
            <span class="muted small">{{
              t('lobby.playersCount', { count: room.players.length, max: room.max_players })
            }}</span>
          </div>
          <div class="player-list">
            <LobbyPlayerCard
              v-for="player in room.players"
              :key="player.id"
              :player="player"
              :is-you="player.id === auth.user?.id"
            /><LobbyPlayerCard
              v-for="seat in Math.max(0, room.max_players - room.players.length)"
              :key="`empty-${seat}`"
            />
          </div>
          <p v-if="lobby.error" class="error" role="alert">{{ lobby.error }}</p>
          <form
            v-if="!me && room.status === 'waiting'"
            class="lobby-actions"
            @submit.prevent="join"
          >
            <AppInput
              v-if="room.is_private"
              v-model="password"
              :label="t('lobby.joinPassword')"
              type="password"
            /><AppButton variant="primary" type="submit" :loading="lobby.loading"
              >{{ t('lobby.joinSubmit') }}</AppButton
            >
          </form>
          <div v-else-if="me && room.status === 'waiting'" class="lobby-actions">
            <AppButton
              :variant="me.is_ready ? 'secondary' : 'primary'"
              :loading="lobby.loading"
              :aria-pressed="me.is_ready"
              @click="ready"
              ><AppIcon name="check" :size="18" />{{
                me.is_ready ? t('lobby.readyDone') : t('lobby.ready')
              }}</AppButton
            ><AppButton
              v-if="me.is_host"
              variant="primary"
              :disabled="!allReady || lobby.loading"
              @click="start"
              >{{ t('lobby.startGame') }}<AppIcon name="play" :size="16" /></AppButton
            ><AppButton variant="danger" :disabled="lobby.loading" @click="leaveOpen = true"
              >{{ t('lobby.leaveLobby') }}</AppButton
            ><small class="ready-hint">{{
              allReady ? t('lobby.everyoneReady') : t('lobby.allMustBeReady')
            }}</small>
          </div>
          <div v-else class="empty-state">
            <p>
              {{ room.status === 'closed' ? t('lobby.closedMessage') : t('lobby.inProgressMessage') }}
            </p>
            <RouterLink to="/dashboard" class="btn btn-secondary">{{ t('lobby.backToPlay') }}</RouterLink>
          </div>
        </section>
        <LobbySettings :lobby="room" /></div></template
    ><AppModal :open="leaveOpen" :title="t('lobby.leaveModal.title')" @close="leaveOpen = false"
      ><p class="muted">{{ t('lobby.leaveModal.description') }}</p>
      <p v-if="lobby.error" class="error" role="alert">{{ lobby.error }}</p>
      <div class="modal-actions">
        <AppButton @click="leaveOpen = false">{{ t('common.stay') }}</AppButton
        ><AppButton variant="danger" :loading="lobby.loading" @click="leave">{{
          t('lobby.leaveLobby')
        }}</AppButton>
      </div></AppModal
    >
  </main>
</template>
