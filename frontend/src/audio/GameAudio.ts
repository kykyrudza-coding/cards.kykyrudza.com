import { shallowReactive } from 'vue'
import { assets } from '../config/assets'
import type { Preferences } from '../stores/preferences'
import { t } from '../i18n'
const paths = {
  deal1: 'cards/deal-01',
  deal2: 'cards/deal-02',
  deal3: 'cards/deal-03',
  flip: 'cards/flip',
  collect: 'cards/collect',
  shuffle: 'cards/shuffle',
  single: 'chips/single',
  bet: 'chips/bet',
  payout: 'chips/payout',
  click: 'ui/click',
  win: 'ui/win',
  lose: 'ui/lose',
  turn: 'ui/turn',
  split: 'ui/split',
  double: 'ui/double',
  push: 'ui/push',
  blackjack: 'ui/blackjack',
  twenty: 'voices/uk/twenty',
  bust: 'voices/uk/bust',
  youWin: 'voices/uk/you-win',
} as const
type Clip = keyof typeof paths
export type Sound = Clip | 'deal'
class GameAudio {
  readonly status = shallowReactive({
    state: 'locked',
    loaded: 0,
    total: Object.keys(paths).length,
    error: '',
    played: 0,
  })
  private context: AudioContext | null = null
  private master: GainNode | null = null
  private effects: GainNode | null = null
  private voices: GainNode | null = null
  private buffers = new Map<Clip, AudioBuffer>()
  private pending = new Map<Clip, Promise<void>>()
  private active = new Set<AudioBufferSourceNode>()
  private voiceActive: AudioBufferSourceNode | null = null
  private preferences: Preferences | null = null
  private lastDeal = 0
  private ensure() {
    if (this.context) return this.context
    const Constructor =
      window.AudioContext ??
      (window as Window & { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Constructor) {
      this.status.error = t('settings.audio.notSupported')
      throw new Error(this.status.error)
    }
    const context = (this.context = new Constructor())
    this.master = context.createGain()
    this.effects = context.createGain()
    this.voices = context.createGain()
    this.effects.connect(this.master)
    this.voices.connect(this.master)
    this.master.connect(context.destination)
    context.addEventListener('statechange', () => {
      this.status.state = context.state === 'running' ? 'ready' : 'locked'
    })
    this.applyGains()
    return context
  }
  configure(preferences: Preferences) {
    this.preferences = { ...preferences }
    this.applyGains()
  }
  private applyGains() {
    if (!this.context || !this.preferences) return
    const p = this.preferences,
      time = this.context.currentTime
    this.master?.gain.setTargetAtTime(p.muted ? 0 : p.master / 100, time, 0.02)
    this.effects?.gain.setTargetAtTime(p.sfx / 100, time, 0.02)
    this.voices?.gain.setTargetAtTime(p.dealerVoice ? p.voice / 100 : 0, time, 0.02)
  }
  async unlock() {
    try {
      const context = this.ensure()
      await context.resume()
      this.status.state = context.state === 'running' ? 'ready' : 'locked'
      void this.preload()
    } catch {
      this.status.error = t('settings.audio.tapToRetry')
    }
  }
  async preload() {
    try {
      this.ensure()
    } catch {
      return
    }
    await Promise.all(Object.keys(paths).map((key) => this.load(key as Clip)))
    if (this.status.loaded === this.status.total) this.status.error = ''
  }
  private load(id: Clip): Promise<void> {
    if (this.buffers.has(id)) return Promise.resolve()
    const running = this.pending.get(id)
    if (running) return running
    const task = (async () => {
      const voice = id === 'twenty' || id === 'bust' || id === 'youWin'
      const ogg = new Audio().canPlayType('audio/ogg; codecs="vorbis"')
      for (const extension of voice ? ['mp3'] : ogg ? ['ogg', 'mp3'] : ['mp3', 'ogg']) {
        try {
          const response = await fetch(assets.audio(`${paths[id]}.${extension}`))
          if (!response.ok) throw new Error(String(response.status))
          const buffer = await this.ensure().decodeAudioData(await response.arrayBuffer())
          this.buffers.set(id, buffer)
          this.status.loaded = this.buffers.size
          return
        } catch {
          /* Try the alternate codec before reporting failure. */
        }
      }
      this.status.error = t('settings.audio.loadFailed', { id })
    })().finally(() => this.pending.delete(id))
    this.pending.set(id, task)
    return task
  }
  play(sound: Sound) {
    if (document.hidden || this.preferences?.muted || this.context?.state !== 'running') return
    let id: Clip
    if (sound === 'deal') {
      const choice = (this.lastDeal + 1 + Math.floor(Math.random() * 2)) % 3
      this.lastDeal = choice
      id = (['deal1', 'deal2', 'deal3'] as const)[choice]!
    } else id = sound
    const buffer = this.buffers.get(id)
    if (!buffer) return
    const voice = id === 'twenty' || id === 'bust' || id === 'youWin'
    if (voice && (!this.preferences?.dealerVoice || this.voiceActive)) return
    if (!voice && this.active.size >= 4) return
    const source = this.context.createBufferSource()
    source.buffer = buffer
    if (!voice) source.playbackRate.value = 0.96 + Math.random() * 0.08
    const gain = this.context.createGain()
    gain.gain.value = id === 'click' ? 0.32 : voice ? 0.9 : 0.9
    source.connect(gain)
    gain.connect((voice ? this.voices : this.effects)!)
    this.active.add(source)
    if (voice) this.voiceActive = source
    source.onended = () => {
      this.active.delete(source)
      if (this.voiceActive === source) this.voiceActive = null
      source.disconnect()
      gain.disconnect()
    }
    source.start()
    this.status.played++
  }
  stop() {
    for (const source of this.active) {
      try {
        source.stop()
      } catch {
        /* Already ended. */
      }
    }
    this.active.clear()
    this.voiceActive = null
  }
  async test(sound: Sound = 'deal') {
    await this.unlock()
    await this.preload()
    this.play(sound)
  }
}
export const gameAudio = new GameAudio()
