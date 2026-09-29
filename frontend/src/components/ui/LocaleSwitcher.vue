<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, setLocale, type Locale } from '../../i18n'
const { locale, t } = useI18n()
const labels: Record<Locale, string> = { uk: 'UA', ru: 'RU', en: 'EN' }
function choose(next: Locale) {
  setLocale(next)
}
</script>
<template>
  <div class="locale-switcher" role="group" :aria-label="t('settings.language.title')">
    <button
      v-for="code in SUPPORTED_LOCALES"
      :key="code"
      type="button"
      :class="{ selected: locale === code }"
      :aria-pressed="locale === code"
      @click="choose(code)"
    >
      {{ labels[code] }}
    </button>
  </div>
</template>
<style scoped>
.locale-switcher {
  display: flex;
  gap: 4px;
  padding: 4px;
  margin: 0 4px 18px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface-2);
}
.locale-switcher button {
  flex: 1;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.4px;
  padding: 7px 0;
}
.locale-switcher button.selected {
  background: var(--surface);
  color: var(--text);
}
.locale-switcher button:hover:not(.selected) {
  color: var(--text);
}
</style>
