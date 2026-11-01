import { test, expect, type Page } from '@playwright/test'
import type { DurakMatchData } from '../src/types/match'

// Fixtures only intercept the test browser. They are never imported by the app.
const user = { id: 1, username: 'Ilya', email: 'ui@example.test', avatar: null }
const names = ['Ilya', 'Sasha', 'Max', 'Anna', 'Dmytro', 'Olena']

function durakMatch(count: number, over = false): DurakMatchData {
  const players = Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    username: names[i]!,
    seat: i,
    status: 'active' as const,
    hand_count: i === 0 ? 7 : 6 - (i % 3),
    ...(i === 0
      ? {
          hand: [
            { rank: '6', suit: 'spades' },
            { rank: '9', suit: 'hearts' },
            { rank: '10', suit: 'clubs' },
            { rank: 'J', suit: 'diamonds' },
            { rank: 'Q', suit: 'hearts' },
            { rank: 'K', suit: 'spades' },
            { rank: 'A', suit: 'hearts' },
          ],
        }
      : {}),
  }))
  return {
    id: 91,
    game_type: 'durak',
    status: 'active',
    round: 1,
    version: 1,
    host_id: 1,
    lobby_code: 'DUR123',
    game: {
      phase: over ? 'finished' : 'attack',
      round: 1,
      trump_suit: 'hearts',
      trump_card: { rank: '8', suit: 'hearts' },
      deck_count: 14,
      table: [
        { attack: { rank: '7', suit: 'clubs' }, defense: { rank: '9', suit: 'clubs' } },
        { attack: { rank: '7', suit: 'diamonds' }, defense: null },
      ],
      players,
      attacker_id: 2 % count || 1,
      defender_id: 1,
      allowed_actions: over ? [] : ['defend', 'take'],
      loser_id: over ? 1 : null,
    },
  } as DurakMatchData
}

async function setup(page: Page, match: DurakMatchData) {
  const state = { match, posts: [] as string[], broadcast: () => {} }
  await page.addInitScript(() => {
    localStorage.setItem('poker.locale', 'en')
    localStorage.setItem('poker.token', 'fixture-token')
  })
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (request.method() === 'POST') state.posts.push(path)
    if (path === '/api/auth/me') return route.fulfill({ json: user })
    if (path.startsWith('/api/matches')) return route.fulfill({ json: state.match })
    return route.fulfill({ status: 404, json: { message: 'Not found' } })
  })
  await page.route('**/broadcasting/auth', (route) =>
    route.fulfill({ json: { auth: 'fixture:signature' } }),
  )
  await page.routeWebSocket(/\/app\//, (socket) => {
    socket.send(
      JSON.stringify({
        event: 'pusher:connection_established',
        data: JSON.stringify({ socket_id: '123.456', activity_timeout: 120 }),
      }),
    )
    socket.onMessage((message) => {
      const event = JSON.parse(String(message))
      if (event.event !== 'pusher:subscribe') return
      state.broadcast = () =>
        socket.send(
          JSON.stringify({ event: 'MatchUpdated', channel: event.data.channel, data: '{}' }),
        )
      socket.send(
        JSON.stringify({
          event: 'pusher_internal:subscription_succeeded',
          channel: event.data.channel,
          data: '{}',
        }),
      )
    })
  })
  return state
}

const shots = process.env.DURAK_SHOTS
const sizes = [
  [1440, 900],
  [1024, 768],
  [390, 844],
  [844, 390],
] as const
for (const [width, height] of sizes) {
  for (const count of [2, 4, 6]) {
    test(`durak with ${count} seats at ${width}x${height} fits the viewport`, async ({ page }) => {
      await page.setViewportSize({ width, height })
      await setup(page, durakMatch(count))
      const errors: string[] = []
      page.on('pageerror', (error) => errors.push(error.message))
      await page.goto('/match/91')
      await expect(page.locator('.durak-table')).toBeVisible()
      await page.waitForTimeout(1200)
      if (shots) await page.screenshot({ path: `${shots}/durak-${count}-${width}x${height}.png` })
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
      )
      expect(overflow).toBeLessThanOrEqual(0)
      const hand = await page.locator('.durak-own-hand').boundingBox()
      expect(hand).not.toBeNull()
      expect(hand!.x).toBeGreaterThanOrEqual(-1)
      expect(hand!.x + hand!.width).toBeLessThanOrEqual(width + 1)
      expect(errors).toEqual([])
    })
  }
}
