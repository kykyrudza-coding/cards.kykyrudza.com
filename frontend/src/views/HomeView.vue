<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { assets } from '../config/assets'
import AppIcon from '../components/ui/AppIcon.vue'
const { t } = useI18n()
const auth = useAuthStore()
</script>
<template>
  <main class="home-page">
    <div class="home-copy">
      <span class="eyebrow"><span class="status-dot" /> {{ t('home.eyebrow') }}</span>
      <h1>{{ t('home.titleLine1') }}<br />{{ t('home.titleLine2') }}</h1>
      <p class="hero-description">
        {{ t('home.description') }}<br />{{ t('home.descriptionLine2') }}<br
          class="desktop-only"
        />
        {{ t('home.descriptionLine3') }}
      </p>
      <div class="button-row">
        <RouterLink :to="auth.isAuthenticated ? '/dashboard' : '/register'" class="btn btn-primary"
          >{{ t('home.startPlaying') }} <AppIcon name="arrow" /></RouterLink
        ><RouterLink to="/login" class="btn btn-ghost">{{ t('home.signIn') }}</RouterLink>
      </div>
      <div class="home-note">
        <AppIcon name="users" :size="18" /> {{ t('home.note') }}
      </div>
    </div>
    <div class="home-art" :aria-label="t('home.artLabel')">
      <div class="home-table" :style="{ backgroundImage: `url(${assets.table()})` }" />
      <span class="home-art-label">{{ t('home.artCaption') }}</span
      ><img class="hero-card card-one" :src="assets.card('A', 'spades')" :alt="t('home.aceAlt')" /><img
        class="hero-card card-two"
        :src="assets.card('K', 'hearts')"
        :alt="t('home.kingAlt')"
      /><img class="hero-chip" :src="assets.chip(25)" :alt="t('home.chipAlt')" />
      <div class="home-art-caption">
        <span class="status-dot" /> {{ t('home.artCaptionGame') }}
        <span class="muted">{{ t('home.artCaptionSub') }}</span>
      </div>
    </div>
    <footer class="home-footer">
      <span>{{ t('home.footerBrand') }}</span><span>{{ t('home.footerTagline') }}</span
      ><span>{{ t('home.footerNoMoney') }}</span>
    </footer>
  </main>
</template>
