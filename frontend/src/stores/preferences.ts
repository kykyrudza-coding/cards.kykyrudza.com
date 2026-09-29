import { defineStore } from 'pinia'
export interface Preferences {
  master: number
  sfx: number
  voice: number
  music: number
  muted: boolean
  dealerVoice: boolean
  cardAnimations: boolean
  chipAnimations: boolean
  reducedMotion: boolean
  shortcuts: boolean
  quality: string
  theme: string
}
const defaults: Preferences = {
  master: 80,
  sfx: 80,
  voice: 70,
  music: 0,
  muted: false,
  dealerVoice: true,
  cardAnimations: true,
  chipAnimations: true,
  reducedMotion: false,
  shortcuts: true,
  quality: 'auto',
  theme: 'dark',
}
function read(): Preferences {
  const result = { ...defaults }
  try {
    const saved: Record<string, unknown> = JSON.parse(
      localStorage.getItem('poker.preferences') ?? '{}',
    )
    for (const key of ['master', 'sfx', 'voice', 'music'] as const)
      if (typeof saved[key] === 'number' && Number.isFinite(saved[key]))
        result[key] = Math.max(0, Math.min(100, saved[key]))
    for (const key of [
      'muted',
      'dealerVoice',
      'cardAnimations',
      'chipAnimations',
      'reducedMotion',
      'shortcuts',
    ] as const)
      if (typeof saved[key] === 'boolean') result[key] = saved[key]
    if (['auto', 'low', 'medium', 'high'].includes(String(saved.quality)))
      result.quality = String(saved.quality)
    if (['dark', 'system'].includes(String(saved.theme))) result.theme = String(saved.theme)
  } catch {
    /* Storage may be unavailable or contain a previous schema. */
  }
  return result
}
export const usePreferencesStore = defineStore('preferences', { state: read })
