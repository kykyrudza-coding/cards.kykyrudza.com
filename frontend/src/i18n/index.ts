import { createI18n } from 'vue-i18n'
import en from '../locales/en'
import uk from '../locales/uk'
import ru from '../locales/ru'

export const SUPPORTED_LOCALES = ['uk', 'ru', 'en'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

const LOCALE_STORAGE_KEY = 'poker.locale'
const DEFAULT_LOCALE: Locale = 'uk'

function isLocale(value: unknown): value is Locale {
  return SUPPORTED_LOCALES.includes(value as Locale)
}

export function getStoredLocale(): Locale {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY)
    if (isLocale(stored)) return stored
  } catch {
    /* localStorage unavailable — fall back to the default locale */
  }
  return DEFAULT_LOCALE
}

export function setStoredLocale(locale: Locale): void {
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  } catch {
    /* Locale choice only lives for this session */
  }
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: getStoredLocale(),
  fallbackLocale: 'en',
  messages: { en, uk, ru },
})

export function setLocale(locale: Locale): void {
  i18n.global.locale.value = locale
  setStoredLocale(locale)
  try {
    document.documentElement.lang = locale
  } catch {
    /* document unavailable outside the browser */
  }
}

/** For use outside components (stores, services, non-reactive classes). */
export const t = i18n.global.t
