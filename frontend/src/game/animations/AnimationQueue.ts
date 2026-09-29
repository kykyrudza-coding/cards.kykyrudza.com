import { gsap } from 'gsap'

export function playTimeline(timeline: gsap.core.Timeline, signal: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    const abort = () => {
      timeline.kill()
      reject(new DOMException('Animation cancelled', 'AbortError'))
    }
    if (signal.aborted) {
      abort()
      return
    }
    signal.addEventListener('abort', abort, { once: true })
    timeline.eventCallback('onComplete', () => {
      signal.removeEventListener('abort', abort)
      resolve()
    })
    timeline.play()
  })
}
export function pause(seconds: number, signal: AbortSignal) {
  return playTimeline(gsap.timeline({ paused: true }).to({}, { duration: seconds }), signal)
}
export class AnimationQueue {
  private controller = new AbortController()
  private tail: Promise<void> = Promise.resolve()
  pending = 0
  private readonly onError: (error: unknown) => void
  constructor(onError: (error: unknown) => void) {
    this.onError = onError
  }
  enqueue(job: (signal: AbortSignal) => Promise<void>) {
    const signal = this.controller.signal
    this.pending++
    this.tail = this.tail
      .then(async () => {
        if (!signal.aborted) await job(signal)
      })
      .catch((error) => {
        if (!signal.aborted) this.onError(error)
      })
      .finally(() => {
        if (!signal.aborted) this.pending--
      })
    return this.tail
  }
  cancel() {
    this.controller.abort()
    this.controller = new AbortController()
    this.tail = Promise.resolve()
    this.pending = 0
  }
}
