<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useLobbyStore } from '../stores/lobby'
import { useUiStore } from '../stores/ui'
import { ApiError } from '../services/api'
import { assets } from '../config/assets'
import type { GameType } from '../types/lobby'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppToggle from '../components/ui/AppToggle.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const auth = useAuthStore()
const lobby = useLobbyStore()
const ui = useUiStore()
const router = useRouter()
const selectedGame = ref<GameType>('blackjack')
// Poker needs an opponent, so its table never starts below two seats.
function selectGame(game: GameType) {
  selectedGame.value = game
  if (game === 'poker' && maxPlayers.value < 2) maxPlayers.value = 2
}
const maxPlayers = ref(4)
const startingChips = ref(5000)
const defaultBet = ref(100)
const eventsEnabled = ref(false)
const isPrivate = ref(false)
const createPassword = ref('')
const createError = ref('')
const joinCode = ref('')
const joinPassword = ref('')
const joinError = ref('')
const pending = ref<'create' | 'join' | null>(null)
const fields = ref<Record<string, string[]>>({})
async function create() {
  pending.value = 'create'
  createError.value = ''
  fields.value = {}
  try {
    const result = await lobby.createLobby({
      game_type: selectedGame.value,
      max_players: maxPlayers.value,
      ...(selectedGame.value !== 'durak'
        ? {
            starting_chips: startingChips.value,
            default_bet: defaultBet.value,
            ...(selectedGame.value === 'blackjack' ? { events_enabled: eventsEnabled.value } : {}),
          }
        : {}),
      is_private: isPrivate.value,
      password: isPrivate.value ? createPassword.value || null : null,
    })
    ui.toast(t('dashboard.create.toastCreated'))
    await router.push(`/lobby/${result.code}`)
  } catch (e) {
    createError.value = e instanceof Error ? e.message : t('dashboard.create.genericError')
    if (e instanceof ApiError) fields.value = e.errors ?? {}
  } finally {
    pending.value = null
  }
}
async function join() {
  pending.value = 'join'
  joinError.value = ''
  try {
    const result = await lobby.joinLobby(
      joinCode.value.replace(/\s/g, '').toUpperCase(),
      joinPassword.value || null,
    )
    await router.push(`/lobby/${result.code}`)
  } catch (e) {
    joinError.value = e instanceof Error ? e.message : t('dashboard.join.genericError')
  } finally {
    pending.value = null
  }
}
</script>
<template>
  <main class="page dashboard-page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('dashboard.eyebrow') }}</span>
        <h1>
          {{ auth.user ? t('dashboard.greetingName', { name: auth.user.username }) : t('dashboard.greetingNoName') }}<span class="accent">.</span>
          {{ t('dashboard.readyToPlay') }}
        </h1>
        <p>{{ t('dashboard.subtitle') }}</p>
      </div>
      <AppBadge>{{ t('common.virtualChipsOnly') }}</AppBadge>
    </header>
    <section class="featured-game">
      <div class="featured-copy">
        <AppBadge tone="success">{{ t('dashboard.featured.availableNow') }}</AppBadge>
        <h2>{{ t('dashboard.featured.title') }}<span class="accent">.</span></h2>
        <p>{{ t('dashboard.featured.description') }}<br />{{ t('dashboard.featured.descriptionLine2') }}</p>
        <div class="game-meta">
          <span><AppIcon name="users" :size="17" />{{ t('dashboard.featured.players') }}</span
          ><span>{{ t('dashboard.featured.quickRounds') }}</span
          ><span>{{ t('dashboard.featured.privateRooms') }}</span>
        </div>
      </div>
      <div class="featured-art" aria-hidden="true">
        <img :src="assets.card('A', 'spades')" alt="" /><img
          :src="assets.card('J', 'hearts')"
          alt=""
        /><img class="featured-chip" :src="assets.chip(25)" alt="" />
      </div>
      <span class="featured-watermark" aria-hidden="true">21</span>
    </section>
    <div class="dashboard-columns">
      <section class="panel create-panel">
        <div class="section-heading">
          <span class="section-icon"><AppIcon name="plus" /></span>
          <div>
            <h2>{{ t('dashboard.create.heading') }}</h2>
            <p>{{ t('dashboard.create.subtitle') }}</p>
          </div>
        </div>
        <form @submit.prevent="create">
          <fieldset class="game-picker">
            <legend>{{ t('dashboard.create.chooseGame') }}</legend>
            <div class="game-options">
              <button
                type="button"
                class="game-option"
                :class="{ selected: selectedGame === 'blackjack' }"
                :aria-pressed="selectedGame === 'blackjack'"
                @click="selectGame('blackjack')"
              >
                <AppIcon name="cards" /><strong>{{ t('dashboard.create.blackjack') }}</strong
                ><small>{{ t('dashboard.create.playersRange') }}</small
                ><AppIcon
                  v-if="selectedGame === 'blackjack'"
                  name="check"
                  class="game-check"
                  :size="16"
              /></button
              ><button
                type="button"
                class="game-option"
                :class="{ selected: selectedGame === 'durak' }"
                :aria-pressed="selectedGame === 'durak'"
                @click="selectGame('durak')"
              >
                <AppIcon name="cards" /><strong>{{ t('dashboard.create.games.durak') }}</strong
                ><small>{{ t('dashboard.create.durakPlayersRange') }}</small
                ><AppIcon
                  v-if="selectedGame === 'durak'"
                  name="check"
                  class="game-check"
                  :size="16"
              /></button
              ><button
                type="button"
                class="game-option"
                :class="{ selected: selectedGame === 'poker' }"
                :aria-pressed="selectedGame === 'poker'"
                @click="selectGame('poker')"
              >
                <AppIcon name="cards" /><strong>{{ t('dashboard.create.games.texasHoldem') }}</strong
                ><small>{{ t('dashboard.create.pokerPlayersRange') }}</small
                ><AppIcon
                  v-if="selectedGame === 'poker'"
                  name="check"
                  class="game-check"
                  :size="16"
              /></button
              ><button
                v-for="name in [
                  t('dashboard.create.games.threeCardPoker'),
                  t('dashboard.create.games.sunduchok'),
                ]"
                :key="name"
                type="button"
                class="game-option"
                disabled
              >
                <AppIcon name="lock" :size="17" /><strong>{{ name }}</strong
                ><small>{{ t('dashboard.create.comingSoon') }}</small>
              </button>
            </div>
          </fieldset>
          <div class="form-grid three">
            <AppInput
              v-model.number="maxPlayers"
              :label="t('dashboard.create.maxPlayers')"
              type="number"
              :min="selectedGame === 'poker' ? 2 : 1"
              max="7"
              required
              :error="fields.max_players?.[0]"
            /><template v-if="selectedGame !== 'durak'">
              <AppInput
                v-model.number="startingChips"
                :label="t('dashboard.create.startingChips')"
                type="number"
                min="1"
                required
                :error="fields.starting_chips?.[0]"
              /><AppInput
                v-model.number="defaultBet"
                :label="
                  selectedGame === 'poker'
                    ? t('dashboard.create.bigBlind')
                    : t('dashboard.create.defaultBet')
                "
                type="number"
                min="2"
                step="2"
                required
                :error="fields.default_bet?.[0]"
              />
            </template>
          </div>
          <AppToggle
            v-if="selectedGame === 'blackjack'"
            v-model="eventsEnabled"
            :label="t('dashboard.create.events')"
          /><AppToggle
            v-model="isPrivate"
            :label="t('dashboard.create.privateLobby')"
            :hint="t('dashboard.create.privateHint')"
          /><AppInput
            v-if="isPrivate"
            v-model="createPassword"
            :label="t('dashboard.create.lobbyPassword')"
            type="password"
            autocomplete="new-password"
            :error="fields.password?.[0]"
          />
          <p v-if="createError" class="error" role="alert">{{ createError }}</p>
          <AppButton
            type="submit"
            variant="primary"
            :loading="pending === 'create'"
            :disabled="!!pending"
            >{{ t('dashboard.create.submit') }}<AppIcon name="arrow" :size="18"
          /></AppButton>
        </form>
      </section>
      <div class="dashboard-side">
        <section class="panel join-panel">
          <div class="section-heading">
            <span class="section-icon"><AppIcon name="link" /></span>
            <div>
              <h2>{{ t('dashboard.join.heading') }}</h2>
              <p>{{ t('dashboard.join.subtitle') }}</p>
            </div>
          </div>
          <form @submit.prevent="join">
            <AppInput
              v-model="joinCode"
              :label="t('dashboard.join.lobbyCode')"
              placeholder="ABC123"
              class="code-field"
              autocomplete="off"
              autocapitalize="characters"
              spellcheck="false"
              required
              @update:model-value="joinCode = String($event).replace(/\s/g, '').toUpperCase()"
            /><AppInput
              v-model="joinPassword"
              :label="t('dashboard.join.password')"
              type="password"
              autocomplete="off"
            />
            <p v-if="joinError" class="error" role="alert">{{ joinError }}</p>
            <AppButton type="submit" :loading="pending === 'join'" :disabled="!!pending"
              >{{ t('dashboard.join.submit') }}<AppIcon name="arrow" :size="18"
            /></AppButton>
          </form>
        </section>
        <div class="fair-play">
          <AppIcon name="users" :size="22" />
          <div>
            <strong>{{ t('dashboard.fairPlay.title') }}</strong>
            <p>{{ t('dashboard.fairPlay.description') }}<br />{{ t('dashboard.fairPlay.descriptionLine2') }}</p>
          </div>
        </div>
      </div>
    </div>
    <section class="panel recent-matches">
      <div class="row-between">
        <h2>{{ t('dashboard.recent.title') }}</h2>
        <span class="muted small">{{ t('dashboard.recent.subtitle') }}</span>
      </div>
      <EmptyState
        :title="t('dashboard.recent.emptyTitle')"
        :description="t('dashboard.recent.emptyDescription')"
      />
    </section>
  </main>
</template>
