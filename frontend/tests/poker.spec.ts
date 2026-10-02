import { test, expect, type Page } from '@playwright/test'
import type { PokerMatchData, PokerPlayerView } from '../src/types/match'

// Fixtures only intercept the test browser. They are never imported by the app.
const user = { id: 1, username: 'Ilya', email: 'ui@example.test', avatar: null }
const names = ['Ilya', 'Sasha', 'Max', 'Anna', 'Dmytro', 'Olena', 'Alex']

type Phase = PokerMatchData['game']['phase']

function pokerMatch(count: number, phase: Phase = 'flop'): PokerMatchData {
  const community = { preflop: 0, flop: 3, turn: 4, river: 5, hand_finished: 5, finished: 5 }[phase]
  const board = [
    { rank: 'A', suit: 'spades' },
    { rank: '10', suit: 'hearts' },
    { rank: '7', suit: 'clubs' },
    { rank: 'K', suit: 'diamonds' },
    { rank: '2', suit: 'hearts' },
  ].slice(0, community)
  const over = phase === 'hand_finished'
  const players: PokerPlayerView[] = Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    username: names[i]!,
    seat: i,
    status: i === 3 ? 'folded' : 'active',
    chips: 4800 - i * 120,
    bet: over || i === 3 ? 0 : i === 1 ? 200 : 0,
    total_bet: 200,
    hand_count: 2,
    ...(i === 0
      ? {
          hand: [
            { rank: 'A', suit: 'hearts' },
            { rank: 'K', suit: 'clubs' },
          ],
        }
      : over && i !== 3
        ? {
            hand: [
              { rank: 'Q', suit: 'spades' },
              { rank: 'J', suit: 'diamonds' },
            ],
          }
        : {}),
    is_dealer: i === 0,
    is_small_blind: i === 1 % count,
    is_big_blind: i === 2 % count,
  }))
  return {
    id: 77,
    game_type: 'poker',
    status: 'active',
    round: 3,
    version: 1,
    host_id: 1,
    lobby_code: 'ABC123',
    game: {
      phase,
      round: 3,
      small_blind: 50,
      big_blind: 100,
      community: board,
      pot: over ? 0 : 600,
      current_bet: 200,
      to_call: 200,
      min_raise_to: 400,
      max_raise_to: 4800,
      players,
      dealer_id: 1,
      current_player_id: over ? null : 1,
      allowed_actions: over ? ['next_hand'] : ['fold', 'call', 'raise', 'all_in'],
      showdown: over,
      results: over ? [{ user_id: 1, amount: 600, hand: 'two_pair' }] : [],
      ready: [],
      winner_id: null,
    },
  }
}

async function setup(page: Page, match: PokerMatchData) {
  const state = {
    match,
    posts: [] as string[],
    bodies: [] as unknown[],
    broadcast: () => {},
  }
  await page.addInitScript(() => {
    localStorage.setItem('poker.locale', 'en')
    localStorage.setItem('poker.token', 'fixture-token')
  })
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (request.method() === 'POST') {
      state.posts.push(path)
      state.bodies.push(request.postDataJSON())
    }
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
      if (event.event === 'pusher:subscribe')
        state.broadcast = () =>
          socket.send(
            JSON.stringify({ event: 'MatchUpdated', channel: event.data.channel, data: '{}' }),
          )
      if (event.event === 'pusher:subscribe')
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

const sizes = [
  [1920, 1080],
  [1440, 900],
  [1024, 768],
  [390, 844],
  [360, 640],
  [844, 390],
] as const

const shots = process.env.POKER_SHOTS
for (const [width, height] of sizes) {
  for (const count of [2, 4, 7]) {
    for (const phase of ['flop', 'hand_finished'] as const) {
      test(`poker ${phase} with ${count} seats at ${width}x${height} fits the viewport`, async ({
        page,
      }) => {
        await page.setViewportSize({ width, height })
        await setup(page, pokerMatch(count, phase))
        const errors: string[] = []
        page.on('pageerror', (error) => errors.push(error.message))
        await page.goto('/match/77')
        await expect(page.locator('.poker-table')).toBeVisible()
        await page.waitForTimeout(1200)
        if (shots)
          await page.screenshot({ path: `${shots}/poker-${phase}-${count}-${width}x${height}.png` })

        // No horizontal scroll, and the action area stays inside the viewport.
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - window.innerWidth,
        )
        expect(overflow).toBeLessThanOrEqual(0)
        const controls = await page.locator('.poker-controls').boundingBox()
        expect(controls).not.toBeNull()
        expect(controls!.y).toBeGreaterThanOrEqual(0)
        expect(controls!.y + controls!.height).toBeLessThanOrEqual(height + 1)
        expect(controls!.x).toBeGreaterThanOrEqual(0)
        expect(controls!.x + controls!.width).toBeLessThanOrEqual(width + 1)

        // Seats must never cover the board or the controls.
        const boxes = async (selector: string) =>
          (await page.locator(selector).all()).map((l) => l.boundingBox())
        const board = await page.locator('.poker-community').boundingBox()
        for (const box of await Promise.all(await boxes('.poker-opponents .poker-seat'))) {
          if (!box || !board) continue
          const overlaps =
            box.x < board.x + board.width &&
            box.x + box.width > board.x &&
            box.y < board.y + board.height &&
            box.y + box.height > board.y
          expect(overlaps, 'an opponent seat overlaps the community cards').toBe(false)
          expect(box.y + box.height).toBeLessThanOrEqual(controls!.y + 1)
        }
        expect(errors).toEqual([])
      })
    }
  }
}

// The realtime socket may not have subscribed yet, so keep nudging until the
// table reflects the new snapshot.
async function pushUntil(state: { broadcast: () => void }, page: Page, cards: number) {
  await expect
    .poll(
      async () => {
        state.broadcast()
        return page.locator('.poker-community .playing-card').count()
      },
      { timeout: 10000 },
    )
    .toBe(cards)
}

test.describe('poker interaction and motion', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('keyboard shortcuts and the raise panel send the right actions', async ({ page }) => {
    const state = await setup(page, pokerMatch(3, 'flop'))
    await page.goto('/match/77')
    await expect(page.locator('.poker-table')).toBeVisible()

    await page.keyboard.press('c')
    await expect.poll(() => state.posts).toContain('/api/matches/77/actions/call')

    // Raise opens the panel first, a second press confirms with the chosen amount.
    await page.keyboard.press('r')
    await expect(page.locator('.poker-raise')).toBeVisible()
    await page.locator('.poker-raise-presets button').first().click()
    await page.getByRole('button', { name: /^Raise to/ }).click()
    await expect.poll(() => state.posts).toContain('/api/matches/77/actions/raise')
    const raise = state.bodies[state.posts.indexOf('/api/matches/77/actions/raise')] as {
      amount: number
    }
    expect(raise.amount).toBeGreaterThanOrEqual(400)
    expect(raise.amount).toBeLessThanOrEqual(4800)

    await page.keyboard.press('f')
    await expect.poll(() => state.posts).toContain('/api/matches/77/actions/fold')
  })

  test('new cards animate in and settle fully visible, leaving no stray chips', async ({
    page,
  }) => {
    const state = await setup(page, pokerMatch(4, 'flop'))
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))
    await page.goto('/match/77')
    await expect(page.locator('.poker-community .playing-card')).toHaveCount(3)

    // Street advances: a turn card arrives and the bets are swept into the pot.
    state.match = pokerMatch(4, 'turn')
    state.match.version = 2
    await pushUntil(state, page, 4)
    await page.waitForTimeout(1500)
    const settled = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('.poker-community .playing-card')].map((card) => {
        const style = getComputedStyle(card)
        return { opacity: style.opacity, transform: style.transform }
      }),
    )
    for (const card of settled) {
      expect(card.opacity).toBe('1')
      expect(['none', 'matrix(1, 0, 0, 1, 0, 0)']).toContain(card.transform)
    }
    expect(await page.locator('.poker-flying-chip').count()).toBe(0)

    // A fresh hand deals new hole cards from the deck.
    state.match = pokerMatch(4, 'preflop')
    state.match.round = 4
    state.match.game.round = 4
    state.match.version = 3
    await expect
      .poll(
        async () => {
          state.broadcast()
          return page.locator('.poker-info small').innerText()
        },
        { timeout: 10000 },
      )
      .toMatch(/pre-flop/i)
    // Motion is really running: some dealt card is mid-flight right after the deal starts.
    await expect
      .poll(
        () =>
          page.evaluate(() =>
            [...document.querySelectorAll<HTMLElement>('.poker-table .playing-card')].some((card) => {
              const style = getComputedStyle(card)
              return Number(style.opacity) < 1 || !['none', 'matrix(1, 0, 0, 1, 0, 0)'].includes(style.transform)
            }),
          ),
        { intervals: [40], timeout: 2000 },
      )
      .toBe(true)
    if (shots) await page.screenshot({ path: `${shots}/poker-dealing.png` })
    await page.waitForTimeout(1800)
    await expect(page.locator('.poker-own-cards .playing-card')).toHaveCount(2)
    const own = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('.poker-own-cards .playing-card')].map(
        (card) => getComputedStyle(card).opacity,
      ),
    )
    expect(own).toEqual(['1', '1'])
    expect(errors).toEqual([])
  })

  test('reduced motion still updates the table instantly', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    const state = await setup(page, pokerMatch(3, 'flop'))
    await page.goto('/match/77')
    await expect(page.locator('.poker-community .playing-card')).toHaveCount(3)
    state.match = pokerMatch(3, 'river')
    state.match.version = 2
    await pushUntil(state, page, 5)
  })
})
