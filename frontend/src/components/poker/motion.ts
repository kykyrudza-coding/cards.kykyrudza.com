import type { InjectionKey } from 'vue'
import type { usePokerMotion } from '../../composables/usePokerMotion'

export const pokerMotionKey: InjectionKey<ReturnType<typeof usePokerMotion>> = Symbol('pokerMotion')
