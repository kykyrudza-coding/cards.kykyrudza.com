import { test, expect, type Page } from '@playwright/test'
import type { DurakMatchData, PlayingCard } from '../src/types/match'

// Fixtures only intercept the test browser. They are never imported by the app.
const user = { id: 1, username: 'Ilya', email: 'ui@example.test', avatar: null }
const names = ['Ilya', 'Sasha', 'Max', 'Anna', 'Dmytro', 'Olena']

type Scenario = 'defend' | 'attack' | 'throwin' | 'translate' | 'taking' | 'wait' | 'finished'

const c = (rank: string, suit: string): PlayingCard => ({ rank, suit })

function durakMatch(count: number, scenario: Scenario = 'defend', id = 91): DurakMatchData {
  const hands: Record<Scenario, PlayingCard[]> = {
    defend: [c('6', 'hearts'), c('10', 'clubs'), c('10', 'diamonds'), c('K', 'spades'), c('Q', 'spades')],
    attack: [c('7', 'clubs'), c('7', 'diamonds'), c('9', 'spades'), c('K', 'spades'), c('A', 'hearts')],
    throwin: [c('7', 'clubs'), c('7', 'diamonds'), c('9', 'spades'), c('K', 'spades')],
    translate: [c('7', 'diamonds'), c('7', 'hearts'), c('K', 'spades'), c('Q', 'spades'), c('J', 'clubs')],
    taking: [c('7', 'diamonds'), c('K', 'spades')],
    wait: [c('6', 'clubs'), c('9', 'hearts'), c('K', 'spades')],
    finished: [c('6', 'clubs')],
  }
  const viewerIsDefender = ['defend', 'translate', 'taking'].includes(scenario)
  const viewerIsAttacker = ['attack', 'throwin'].includes(scenario)
  const attackerId = viewerIsAttacker ? 1 : count
  const defenderId = viewerIsDefender ? 1 : viewerIsAttacker ? 2 : 2
  const throwers = viewerIsDefender
    ? [...new Set([count, 2])]
    : viewerIsAttacker
      ? [1]
      : [...new Set([1, 3 > count ? 1 : 3])]

  const players = Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    username: names[i]!,
    seat: i,
    status: 'active' as const,
    hand_count: i === 0 ? hands[scenario].length : 6 - (i % 3),
    ...(i === 0 ? { hand: hands[scenario] } : {}),
  }))

  const table = {
    defend: [
      { attack: c('9', 'clubs'), defense: null },
      { attack: c('9', 'diamonds'), defense: null },
    ],
    attack: [],
    throwin: [{ attack: c('7', 'hearts'), defense: c('9', 'hearts') }],
    translate: [{ attack: c('7', 'clubs'), defense: null }],
    taking: [{ attack: c('9', 'clubs'), defense: null }],
    wait: [{ attack: c('8', 'clubs'), defense: null }],
    finished: [],
  }[scenario]

  const allowed = {
    defend: ['defend', 'take'],
    attack: ['attack'],
    throwin: ['attack', 'pass'],
    translate: ['defend', 'take', 'translate'],
    taking: [],
    wait: [],
    finished: [],
  }[scenario] as DurakMatchData['game']['allowed_actions']

  const hints: DurakMatchData['game']['hints'] = {
    defend: {
      playable: [],
      defenses: [
        { attack: c('9', 'clubs'), with: [c('6', 'hearts'), c('10', 'clubs')] },
        { attack: c('9', 'diamonds'), with: [c('6', 'hearts'), c('10', 'diamonds')] },
      ],
      translate: [],
    },
    attack: { playable: hands.attack, defenses: [], translate: [] },
    throwin: { playable: [c('7', 'clubs'), c('7', 'diamonds')], defenses: [], translate: [] },
    translate: {
      playable: [],
      defenses: [{ attack: c('7', 'clubs'), with: [c('7', 'hearts')] }],
      translate: [c('7', 'diamonds'), c('7', 'hearts')],
    },
    taking: { playable: [], defenses: [], translate: [] },
    wait: { playable: [], defenses: [], translate: [] },
    finished: { playable: [], defenses: [], translate: [] },
  }[scenario]

  const serverTime = Math.floor(Date.now() / 1000)
  return {
    id,
    game_type: 'durak',
    status: 'active',
    round: 3,
    version: 1,
    host_id: 1,
    lobby_code: 'DUR123',
    next_match_id: null,
    game: {
      phase: scenario === 'finished' ? 'finished' : 'attack',
      round: 3,
      trump_suit: 'hearts',
      trump_card: c('8', 'hearts'),
      deck_count: 14,
      discard_count: 8,
      table,
      players,
      attacker_id: attackerId,
      defender_id: defenderId,
      throwers: scenario === 'finished' ? [] : throwers,
      passed: [],
      taking: scenario === 'taking',
      attack_limit: 6,
      allowed_actions: allowed,
      hints,
      log: [
        { user_id: attackerId, action: 'attack', cards: [c('9', 'clubs')], auto: false, round: 3 },
        { user_id: defenderId, action: 'defend', cards: [c('10', 'clubs')], auto: false, round: 3 },
        { user_id: 2, action: 'pass', cards: [], auto: false, round: 3 },
      ],
      loser_id: scenario === 'finished' ? 1 : null,
      turn_deadline: scenario === 'finished' ? null : serverTime + 40,
      turn_seconds: 60,
      server_time: serverTime,
    },
  } as DurakMatchData
}

interface Posted {
  path: string
  body: unknown
}

async function setup(page: Page, match: DurakMatchData) {
  const state = { match, posts: [] as Posted[], broadcast: () => {} }
  await page.addInitScript(() => {
    localStorage.setItem('poker.locale', 'en')
    localStorage.setItem('poker.token', 'fixture-token')
  })
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (request.method() === 'POST')
      state.posts.push({ path, body: request.postDataJSON() ?? null })
    if (path === '/api/auth/me') return route.fulfill({ json: user })
    if (path.endsWith('/rematch'))
      return route.fulfill({ json: { ...state.match, id: 92, round: 1 } })
    const asked = Number(path.split('/').pop())
    if (path.startsWith('/api/matches/') && asked === 92)
      return route.fulfill({ json: { ...state.match, id: 92, round: 1 } })
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

const hand = (page: Page, label: string) => page.locator(`button.hand-card[aria-label="${label}"]`)

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
      await setup(page, durakMatch(count, 'defend'))
      const errors: string[] = []
      page.on('pageerror', (error) => errors.push(error.message))
      await page.goto('/match/91')
      await expect(page.locator('.durak-table')).toBeVisible()
      await page.waitForTimeout(1000)
      if (shots) await page.screenshot({ path: `${shots}/durak-${count}-${width}x${height}.png` })
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
      )
      expect(overflow).toBeLessThanOrEqual(0)
      const row = await page.locator('.durak-hand').boundingBox()
      expect(row).not.toBeNull()
      expect(row!.x).toBeGreaterThanOrEqual(-1)
      expect(row!.x + row!.width).toBeLessThanOrEqual(width + 1)
      // Every opponent is on screen — none hidden behind the table's centre.
      const seats = page.locator('.durak-opponents .durak-seat')
      await expect(seats).toHaveCount(count - 1)
      for (const seat of await seats.all()) {
        const box = await seat.boundingBox()
        expect(box).not.toBeNull()
        expect(box!.x).toBeGreaterThanOrEqual(-1)
        expect(box!.x + box!.width).toBeLessThanOrEqual(width + 1)
      }
      expect(errors).toEqual([])
    })
  }
}

test.describe('durak controls', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('each of two cards can be beaten with its own card, in any order', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'defend'))
    await page.goto('/match/91')
    await expect(page.locator('.bf-slot')).toHaveCount(2)

    // Cards that beat nothing are dimmed; the rest are lit.
    await expect(hand(page, 'K spades')).toHaveClass(/dim/)
    await expect(hand(page, '10 diamonds')).toHaveClass(/playable/)

    // The second card is beaten first: 10♦ only beats 9♦, so it goes straight there.
    await hand(page, '10 diamonds').click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]).toEqual({
      path: '/api/matches/91/actions/defend',
      body: { attack: c('9', 'diamonds'), defense: c('10', 'diamonds') },
    })
  })

  test('a trump that beats both cards asks which one to beat', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'defend'))
    await page.goto('/match/91')

    await hand(page, '6 hearts').click()
    await expect(page.locator('.bf-slot.target')).toHaveCount(2)
    expect(state.posts).toHaveLength(0) // nothing sent until a target is chosen

    await page.locator('.bf-slot').nth(1).click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]).toEqual({
      path: '/api/matches/91/actions/defend',
      body: { attack: c('9', 'diamonds'), defense: c('6', 'hearts') },
    })
  })

  test('picking the card to beat first narrows the hand to what beats it', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'defend'))
    await page.goto('/match/91')

    await page.locator('.bf-slot').first().click() // 9♣: 6♥ or 10♣
    await expect(hand(page, '10 diamonds')).toHaveClass(/dim/)
    await expect(hand(page, '10 clubs')).toHaveClass(/playable/)

    await hand(page, '10 clubs').click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]!.body).toEqual({ attack: c('9', 'clubs'), defense: c('10', 'clubs') })
  })

  test('an illegal card sends nothing and says why', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'defend'))
    await page.goto('/match/91')
    await hand(page, 'K spades').click()
    await expect(page.locator('.durak-status')).toContainText('does not beat')
    expect(state.posts).toHaveLength(0)
  })

  test('translate with a chosen card', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'translate'))
    await page.goto('/match/91')

    await page.getByRole('button', { name: /^Translate/ }).click()
    await expect(hand(page, '7 diamonds')).toHaveClass(/playable/)
    await expect(hand(page, 'K spades')).toHaveClass(/dim/)

    await hand(page, '7 diamonds').click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]).toEqual({
      path: '/api/matches/91/actions/translate',
      body: { cards: [c('7', 'diamonds')] },
    })
  })

  test('take and pass buttons post the right action', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'defend'))
    await page.goto('/match/91')
    await page.getByRole('button', { name: /^Take/ }).click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]!.path).toBe('/api/matches/91/actions/take')

    const second = await setup(page, durakMatch(4, 'throwin'))
    await page.goto('/match/91')
    await page.getByRole('button', { name: /^Beaten off/ }).click()
    await expect.poll(() => second.posts.length).toBe(1)
    expect(second.posts[0]!.path).toBe('/api/matches/91/actions/pass')
  })

  test('opening with several cards of one rank', async ({ page }) => {
    const state = await setup(page, durakMatch(4, 'attack'))
    await page.goto('/match/91')

    await expect(page.locator('.rank-group')).toHaveCount(1) // the pair of sevens
    await page.locator('.rank-group').click()
    await expect.poll(() => state.posts.length).toBe(1)
    expect(state.posts[0]).toEqual({
      path: '/api/matches/91/actions/attack',
      body: { cards: [c('7', 'clubs'), c('7', 'diamonds')] },
    })
  })

  test('a throw-in only lights the matching ranks', async ({ page }) => {
    await setup(page, durakMatch(4, 'throwin'))
    await page.goto('/match/91')
    await expect(hand(page, '7 clubs')).toHaveClass(/playable/)
    await expect(hand(page, 'K spades')).toHaveClass(/dim/)
  })

  test('the neighbours and the attacker are marked, and everyone is on screen', async ({ page }) => {
    await setup(page, durakMatch(4, 'wait'))
    await page.goto('/match/91')
    await expect(page.locator('.durak-seat[data-role="attacking"]')).toHaveCount(1)
    await expect(page.locator('.durak-seat[data-role="defending"]')).toHaveCount(1)
    await expect(page.locator('.durak-log li')).toHaveCount(3)
  })

  test('after the game a rematch starts a new one in place', async ({ page }) => {
    const state = await setup(page, durakMatch(3, 'finished'))
    await page.goto('/match/91')
    await expect(page.locator('.durak-result')).toContainText('You are the Durak')
    await page.getByRole('button', { name: 'Play again' }).click()
    await expect(page).toHaveURL(/\/match\/92/)
    expect(state.posts.some((p) => p.path === '/api/matches/91/rematch')).toBe(true)
  })

  test('others are taken to the new game once someone starts it', async ({ page }) => {
    const finished = durakMatch(3, 'finished')
    const state = await setup(page, finished)
    await page.goto('/match/91')
    await expect(page.locator('.durak-result')).toBeVisible()
    state.match = { ...finished, next_match_id: 92 }
    await expect
      .poll(
        async () => {
          state.broadcast()
          return page.url()
        },
        { timeout: 10000 },
      )
      .toMatch(/\/match\/92/)
  })
})
