import { test, expect, type Page } from '@playwright/test'
import type { Lobby } from '../src/types/lobby'
import type { MatchData, BlackjackHandView } from '../src/types/match'

// Fixtures only intercept the test browser. They are never imported by the app.
const user = { id: 1, username: 'Ilya', email: 'ui@example.test', avatar: null }
const hand = (): BlackjackHandView => ({
  cards: [
    { rank: '8', suit: 'clubs' },
    { rank: '8', suit: 'diamonds' },
  ],
  score: 16,
  bet: 100,
  status: 'playing',
  result: null,
  is_split: false,
  is_active: false,
})
const initialMatch = (count = 4): MatchData => ({
  id: 42,
  game_type: 'blackjack',
  status: 'active',
  round: 2,
  version: 1,
  host_id: 1,
  manual_bets: true,
  default_bet: 100,
  confirmed_bets: {},
  game: {
    phase: 'player_turn',
    round: 2,
    dealer: { cards: [{ rank: '10', suit: 'hearts' }, { hidden: true }], score: null },
    players: Array.from({ length: count }, (_, index) => ({
      id: index + 1,
      username: ['Ilya', 'Sasha', 'Max', 'Anna', 'Dmytro', 'Olena', 'Alex'][index]!,
      seat: index,
      chips: 4900,
      status: 'active',
      hands: [{ ...hand(), is_active: index === 0 }],
    })),
    current_player_id: 1,
    current_hand_index: 0,
    allowed_actions: ['hit', 'stand', 'double', 'split'],
  },
})
const initialLobby = (): Lobby => ({
  id: 5,
  code: 'ABC123',
  game_type: 'blackjack',
  status: 'waiting',
  max_players: 4,
  starting_chips: 5000,
  default_bet: 100,
  is_private: false,
  match_id: null,
  host: { id: 1, username: 'Ilya' },
  players: [
    { id: 1, username: 'Ilya', seat: 0, is_ready: false, is_host: true },
    { id: 2, username: 'Sasha', seat: 1, is_ready: true, is_host: false },
  ],
})
async function setup(page: Page, count = 4, signedIn = true) {
  const state = {
    match: initialMatch(count),
    lobby: initialLobby(),
    posts: [] as string[],
    matchGets: 0,
    missing: false,
    failNext: false,
    confirmGuests: true,
    broadcast: () => {},
    expectedRounds: [] as number[],
  }
  await page.addInitScript(() => localStorage.setItem('poker.locale', 'en'))
  if (signedIn) await page.addInitScript(() => localStorage.setItem('poker.token', 'fixture-token'))
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (request.method() === 'POST') state.posts.push(path)
    if (path === '/api/auth/me') return route.fulfill({ json: user })
    if (path === '/api/auth/register' || path === '/api/auth/login')
      return route.fulfill({ json: { user, token: 'fixture-token' } })
    if (path === '/api/auth/logout') return route.fulfill({ json: { message: 'Logged out' } })
    if (path.startsWith('/api/lobbies')) {
      if (state.missing)
        return route.fulfill({ status: 404, json: { message: 'Lobby not found.' } })
      if (path.endsWith('/ready')) state.lobby.players[0]!.is_ready = request.postDataJSON().ready
      if (path.endsWith('/start')) return route.fulfill({ json: state.match })
      if (path.endsWith('/leave')) return route.fulfill({ json: { message: 'Left lobby' } })
      return route.fulfill({ json: state.lobby })
    }
    if (path.startsWith('/api/matches')) {
      if (state.missing)
        return route.fulfill({ status: 404, json: { message: 'Match not found.' } })
      if (request.method() === 'GET') state.matchGets++
      if (path.endsWith('/actions/hit')) {
        state.match.game.players[0]!.hands[0]!.cards.push({ rank: '2', suit: 'spades' })
        state.match.game.players[0]!.hands[0]!.score = 18
      }
      if (path.endsWith('/actions/double')) {
        state.match.game.players[0]!.hands[0]!.bet = 200
        state.match.game.players[0]!.chips = 4800
        state.match.game.players[0]!.hands[0]!.cards.push({ rank: '3', suit: 'spades' })
      }
      if (path.endsWith('/actions/split')) {
        state.match.game.players[0]!.hands = [
          { ...hand(), is_active: true, is_split: true },
          { ...hand(), is_active: false, is_split: true },
        ]
      }
      if (path.endsWith('/actions/stand')) {
        state.match.game.phase = 'round_finished'
        state.match.game.current_player_id = null
        state.match.game.allowed_actions = []
        state.match.game.dealer = {
          cards: [
            { rank: '10', suit: 'hearts' },
            { rank: '8', suit: 'clubs' },
          ],
          score: 18,
        }
        state.match.game.players[0]!.hands = [
          { ...hand(), score: 20, status: 'finished', result: 'win', profit: 100 },
        ]
      }
      if (path.endsWith('/bet')) {
        state.expectedRounds.push(request.postDataJSON().expected_round)
        if (state.failNext)
          return route.fulfill({ status: 503, json: { message: 'Try the next deal again.' } })
        const amount = request.postDataJSON().amount
        state.match.confirmed_bets = { 1: amount }
        if (!state.confirmGuests) {
          state.match.version++
          return route.fulfill({ json: state.match })
        }
        const version = state.match.version
        state.match = initialMatch(count)
        state.match.round = 3
        state.match.game.round = 3
        state.match.version = version
      }
      if (path.endsWith('/finish')) state.match.status = 'finished'
      if (request.method() === 'POST') state.match.version++
      return route.fulfill({ json: state.match })
    }
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
  [430, 932],
  [844, 390],
] as const
for (const [width, height] of sizes) {
  test(`all routes and seven seats at ${width}x${height}`, async ({ page }) => {
    await page.setViewportSize({ width, height })
    await setup(page, 7)
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))
    for (const route of [
      '/',
      '/login',
      '/register',
      '/dashboard',
      '/lobby/ABC123',
      '/profile',
      '/statistics',
      '/achievements',
      '/collection',
      '/settings',
      '/match/42',
    ]) {
      await page.goto(route)
      await page.evaluate(() => document.fonts.ready)
      await expect(page.locator('main')).toBeVisible()
      if (route.startsWith('/match')) {
        await expect(page.locator('.own-seat')).toBeVisible()
        await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeEnabled({
          timeout: 12000,
        })
      } else if (route.startsWith('/lobby'))
        await expect(page.locator('.lobby-player')).toHaveCount(4)
      else await expect(page.locator('h1').first()).toBeVisible()
      expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
        route,
      ).toBe(true)
      if (route.startsWith('/match')) {
        expect(
          await page.evaluate(() => document.documentElement.scrollHeight <= innerHeight),
        ).toBe(true)
        await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeInViewport()
        await expect(page.getByRole('button', { name: 'Split', exact: true })).toBeInViewport()
        const table = await page.locator('.game-playfield').boundingBox()
        expect(table!.height).toBeGreaterThan(250)
        const dealer = await page.locator('.dealer-area').boundingBox()
        const own = await page.locator('.own-seat').boundingBox()
        expect(own!.y).toBeGreaterThan(dealer!.y + dealer!.height)
      }
    }
    await expect(page.locator('.opponent-seats .player-seat')).toHaveCount(6)
    expect(
      await page
        .locator('img')
        .evaluateAll((images) => images.every((image) => image.complete && image.naturalWidth > 0)),
    ).toBe(true)
    await page.screenshot({ path: `test-results/match-${width}x${height}.png` })
    expect(errors).toEqual([])
  })
}

test('register, login, validation and protected invite redirects', async ({ page }) => {
  await setup(page, 4, false)
  await page.goto('/lobby/ABC123')
  await expect(page).toHaveURL(/login\?redirect=/)
  await page.getByRole('link', { name: 'Create account', exact: true }).last().click()
  await page.getByLabel('Username', { exact: true }).fill('Test_User')
  await page.getByLabel('Email', { exact: true }).fill('ui@example.test')
  await page.getByLabel('Password', { exact: true }).fill('test-password')
  await page.getByLabel('Confirm password').fill('wrong-password')
  await page.getByRole('button', { name: 'Create account' }).click()
  await expect(page.getByText('Passwords do not match.')).toBeVisible()
  await page.getByLabel('Confirm password').fill('test-password')
  await page.getByRole('button', { name: 'Create account' }).click()
  await expect(page).toHaveURL(/lobby\/ABC123/)
  await page.goto('/profile')
  await page.getByRole('button', { name: 'Log out', exact: true }).last().click()
  await page.getByRole('dialog').getByRole('button', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/login/)
  await page.getByLabel('Email').fill('ui@example.test')
  await page.getByLabel('Password').fill('test-password')
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
  await expect(page).toHaveURL(/dashboard/)
})

test('create, ready, start, actions, split, results, next round, finish and leave', async ({
  page,
}) => {
  const state = await setup(page)
  await page.goto('/dashboard')
  await page.getByRole('button', { name: 'Create lobby', exact: true }).click()
  await expect(page).toHaveURL(/lobby\/ABC123/)
  await expect(page.getByRole('button', { name: 'Start game' })).toBeDisabled()
  await page.getByRole('button', { name: 'Ready', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Start game' })).toBeEnabled()
  await page.getByRole('button', { name: 'Start game' }).click()
  await expect(page.locator('.own-seat')).toBeVisible()
  for (const action of ['Hit', 'Double', 'Split']) {
    await page.getByRole('button', { name: action, exact: true }).click()
    await expect
      .poll(() => state.posts.includes(`/api/matches/42/actions/${action.toLowerCase()}`))
      .toBe(true)
  }
  await expect(page.locator('.own-seat .hand-view')).toHaveCount(2)
  await expect(page.locator('.own-seat .active-hand')).toHaveCount(1)
  await page.getByRole('button', { name: 'Stand', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await expect(page.locator('.own-seat .inline-result')).toContainText('+100')
  await expect(dialog).not.toBeVisible()
  await page.getByRole('button', { name: 'Confirm bet', exact: true }).click()
  await expect(page.getByText('Round 3', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Game settings' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: 'Finish match' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Finish match' }).click()
  await expect(page.locator('.action-status')).toHaveText('Match complete')
  await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeDisabled()
  await page.getByRole('button', { name: 'Leave', exact: true }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Leave table' }).click()
  await expect(page).toHaveURL(/dashboard/)
})

test('join, leave confirmation, unavailable routes and retry', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/dashboard')
  await page.getByLabel('Lobby code').fill(' a b c123 ')
  await expect(page.getByLabel('Lobby code')).toHaveValue('ABC123')
  await page.getByRole('button', { name: 'Join lobby', exact: true }).click()
  await expect(page).toHaveURL(/lobby\/ABC123/)
  await page.getByRole('button', { name: 'Leave lobby', exact: true }).click()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).not.toBeVisible()
  await page.getByRole('button', { name: 'Leave lobby', exact: true }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Leave lobby' }).click()
  await expect(page).toHaveURL(/dashboard/)
  expect(state.posts).toContain('/api/lobbies/ABC123/leave')
  state.missing = true
  await page.goto('/lobby/WRONG1')
  await expect(page.getByText('Lobby unavailable')).toBeVisible()
  await page.goto('/match/42')
  await expect(page.getByText('Table unavailable')).toBeVisible()
  state.missing = false
  await page.getByRole('button', { name: 'Try again' }).click()
  await expect(page.locator('.own-seat')).toBeVisible()
})

test('preferences persist, dialog focus is trapped, opt-in shortcuts and waiting state', async ({
  page,
}) => {
  const state = await setup(page)
  await page.goto('/match/42')
  await expect(page.locator('.own-seat')).toBeVisible()
  await page.locator('main').click({ position: { x: 10, y: 200 } })
  expect(state.posts).toHaveLength(0)
  await page.getByRole('button', { name: 'Game settings' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await dialog.getByRole('switch', { name: 'Keyboard shortcuts' }).check()
  await dialog.getByRole('switch', { name: 'Reduced motion' }).check()
  for (let i = 0; i < 20; i++) {
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => !!document.activeElement?.closest('dialog'))).toBe(true)
  }
  await page.keyboard.press('Escape')
  await expect(page.getByRole('button', { name: 'Game settings' })).toBeFocused()
  await page.reload()
  await expect(page.locator('.own-seat')).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.dataset.reducedMotion)).toBe('true')
  await page.locator('main').click({ position: { x: 10, y: 200 } })
  await expect(page.getByRole('button', { name: /^Hit/ })).toBeEnabled()
  await page.keyboard.press('h')
  await expect.poll(() => state.posts.includes('/api/matches/42/actions/hit')).toBe(true)
  state.match.game.current_player_id = 2
  state.match.game.allowed_actions = []
  await page.reload()
  await expect(page.getByText('Waiting for Sasha…')).toBeVisible()
  await expect(page.getByRole('button', { name: /^Hit/ })).toBeDisabled()
})

test('audio unlock, decode, mute and duplicate realtime snapshots', async ({ page }) => {
  const state = await setup(page, 2)
  await page.goto('/match/42')
  await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeEnabled({
    timeout: 10000,
  })
  await page.getByRole('button', { name: 'Game settings' }).click()
  await page.getByRole('button', { name: 'Enable sound / Test' }).click()
  const audioStatus = page.getByRole('dialog').getByRole('status')
  await expect(audioStatus).toContainText('15/15 clips ready')
  await expect(audioStatus).toContainText('Sound ready')
  const played = Number((await audioStatus.innerText()).match(/(\d+) played/)![1])
  expect(played).toBeGreaterThan(0)
  await page.getByRole('switch', { name: 'Mute all' }).check()
  const mutedCount = Number((await audioStatus.innerText()).match(/(\d+) played/)![1])
  await page.getByRole('button', { name: 'Enable sound / Test' }).click()
  await expect(audioStatus).toContainText(`${mutedCount} played`)
  await page.keyboard.press('Escape')
  const gets = state.matchGets
  state.broadcast()
  state.broadcast()
  await expect.poll(() => state.matchGets).toBeGreaterThan(gets)
  await expect(page.locator('main')).toHaveAttribute('data-presentation-phase', 'playing')
  await expect(page.locator('.own-seat [data-card-id]')).toHaveCount(2)
  await page.getByRole('button', { name: 'Hit', exact: true }).click()
  await expect(page.locator('.own-seat [data-card-id]')).toHaveCount(3)
  await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeEnabled()
})

test('resize cancels dealing; confirmed bet retries with expected round', async ({ page }) => {
  const state = await setup(page, 7)
  state.failNext = true
  await page.goto('/match/42')
  await expect(page.locator('main')).toHaveAttribute('data-presentation-phase', 'dealing')
  await page.setViewportSize({ width: 1024, height: 768 })
  await expect(page.getByRole('button', { name: 'Stand', exact: true })).toBeEnabled()
  await expect(page.locator('.own-seat [data-card-id]')).toHaveCount(2)
  await expect(page.locator('[data-card-id]')).toHaveCount(16)
  await page.getByRole('button', { name: 'Stand', exact: true }).click()
  await expect(page.locator('.own-seat .inline-result')).toContainText('+100')
  await expect(page.getByRole('button', { name: 'Confirm bet', exact: true })).toBeVisible({
    timeout: 12000,
  })
  expect(state.expectedRounds).toEqual([])
  await page.getByRole('button', { name: 'Confirm bet', exact: true }).click()
  await expect(page.locator('.bet-controls .error')).toHaveText('Try the next deal again.')
  await expect(page.getByRole('dialog')).not.toBeVisible()
  expect(state.expectedRounds).toEqual([2])
  await expect(page.locator('[data-card-id]')).toHaveCount(0)
  state.failNext = false
  await page.getByRole('button', { name: 'Confirm bet', exact: true }).click()
  await expect(page.getByText('Round 3', { exact: true })).toBeVisible()
  expect(state.expectedRounds).toEqual([2, 2])
})

test('background settlement restores betting and waits for confirmation', async ({ page }) => {
  const state = await setup(page, 2)
  await page.goto('/match/42')
  await expect(page.getByRole('button', { name: 'Stand', exact: true })).toBeEnabled({
    timeout: 10000,
  })
  await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: true })
    document.dispatchEvent(new Event('visibilitychange'))
  })
  state.match.game.phase = 'round_finished'
  state.match.game.allowed_actions = []
  state.match.game.current_player_id = null
  state.match.game.players[0]!.hands[0]!.result = 'push'
  state.match.game.players[0]!.hands[0]!.profit = 0
  state.match.game.dealer = {
    cards: [
      { rank: '10', suit: 'hearts' },
      { rank: '6', suit: 'clubs' },
    ],
    score: 16,
  }
  state.match.version++
  const before = state.matchGets
  state.broadcast()
  await expect.poll(() => state.matchGets).toBeGreaterThan(before)
  await expect(page.locator('.own-seat .inline-result')).toContainText('±0')
  expect(state.expectedRounds).toEqual([])
  await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    document.dispatchEvent(new Event('visibilitychange'))
  })
  await expect(page.getByRole('button', { name: 'Confirm bet', exact: true })).toBeVisible({
    timeout: 10000,
  })
  expect(state.expectedRounds).toEqual([])
  await page.getByRole('button', { name: 'Confirm bet', exact: true }).click()
  await expect(page.getByText('Round 3', { exact: true })).toBeVisible({ timeout: 10000 })
  state.broadcast()
  await expect(page.getByRole('button', { name: 'Stand', exact: true })).toBeEnabled({
    timeout: 10000,
  })
  expect(state.expectedRounds).toEqual([2])
})

test('bet steps cross 1000 in both directions and confirmation survives realtime', async ({
  page,
}) => {
  const state = await setup(page, 2)
  state.confirmGuests = false
  await page.goto('/match/42')
  await page.getByRole('button', { name: 'Stand', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Confirm bet', exact: true })).toBeVisible({
    timeout: 12000,
  })
  expect(state.expectedRounds).toEqual([])
  const selected = page.getByLabel('Selected bet')
  await expect(selected).toHaveText('100')
  for (let i = 0; i < 9; i++)
    await page.getByRole('button', { name: 'Increase bet', exact: true }).click()
  await expect(selected).toHaveText('1,000')
  await page.getByRole('button', { name: 'Increase bet', exact: true }).click()
  await expect(selected).toHaveText('1,500')
  state.match.version++
  state.broadcast()
  await expect.poll(() => state.matchGets).toBeGreaterThan(1)
  await expect(selected).toHaveText('1,500')
  await page.getByRole('button', { name: 'Decrease bet', exact: true }).click()
  await expect(selected).toHaveText('1,000')
  await page.getByRole('button', { name: 'Decrease bet', exact: true }).click()
  await expect(selected).toHaveText('900')
  await page.screenshot({ path: 'test-results/betting-desktop.png' })
  await page.getByRole('button', { name: 'Confirm bet', exact: true }).click()
  await expect(page.getByText('Bet confirmed: 900')).toBeVisible()
  await expect(page.getByText('Waiting for Sasha…')).toBeVisible()
  await expect(page.locator('[data-card-id]')).toHaveCount(0)
  expect(state.match.confirmed_bets?.['1']).toBe(900)
  await page.reload()
  await expect(page.getByText('Bet confirmed: 900')).toBeVisible({ timeout: 12000 })
  await expect(page.getByRole('button', { name: 'Confirm bet', exact: true })).toHaveCount(0)
  expect(state.expectedRounds).toEqual([2])
})

test('cold card textures finish decoding before the deal starts', async ({ page }) => {
  await setup(page, 2)
  let release: () => void = () => {}
  const gate = new Promise<void>((resolve) => {
    release = resolve
  })
  let requests = 0
  await page.route('**/blackjack/cards/**/*.svg', async (route) => {
    requests++
    await gate
    await route.continue()
  })
  await page.goto('/match/42')
  await expect.poll(() => requests).toBeGreaterThan(40)
  await expect(page.locator('main')).toHaveAttribute('data-presentation-phase', 'dealing')
  await expect(page.locator('[data-card-id]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeDisabled()
  release()
  await expect(page.getByRole('button', { name: 'Hit', exact: true })).toBeEnabled({
    timeout: 12000,
  })
  expect(
    await page
      .locator('.playing-card img')
      .evaluateAll((images) =>
        images.every(
          (image) =>
            (image as HTMLImageElement).complete && (image as HTMLImageElement).naturalWidth > 0,
        ),
      ),
  ).toBe(true)
  await expect(page.locator('[data-card-id="dealer-1"]')).toHaveClass(/is-hidden/)
})

for (const [width, height] of [
  [1440, 900],
  [1024, 768],
  [390, 844],
  [844, 390],
]) {
  test(`table polish: split and settlement at ${width}x${height}`, async ({ page }) => {
    await page.setViewportSize({ width: width!, height: height! })
    const state = await setup(page, 7)
    state.match.game.players[0]!.hands = [
      { ...hand(), is_split: true, is_active: true },
      { ...hand(), is_split: true, is_active: false },
    ]
    await page.goto('/match/42')
    await expect(page.locator('.own-seat .hand-view')).toHaveCount(2)
    await expect(page.locator('.own-seat .active-hand')).toHaveCount(1)
    const own = await page.locator('.own-seat').boundingBox()
    const controls = await page.locator('.action-bar').boundingBox()
    expect(own!.y + own!.height).toBeLessThanOrEqual(height! - 8)
    if (height! > 550) expect(own!.y + own!.height).toBeLessThanOrEqual(controls!.y + 5)
    await page.screenshot({ path: `test-results/polish-split-${width}.png` })
    await page.evaluate(() => {
      Object.defineProperty(document, 'hidden', { configurable: true, value: true })
      document.dispatchEvent(new Event('visibilitychange'))
    })
    for (const [i, player] of state.match.game.players.entries())
      for (const h of player.hands) {
        h.is_active = false
        h.status = 'stood'
        h.result = i % 2 ? 'lose' : 'win'
        h.profit = i % 2 ? -100 : 100
      }
    state.match.game.phase = 'round_finished'
    state.match.game.allowed_actions = []
    state.match.game.current_player_id = null
    state.match.version++
    state.match.game.dealer = {
      cards: [
        { rank: '10', suit: 'hearts' },
        { rank: '8', suit: 'clubs' },
      ],
      score: 18,
      status: 'stood',
    }
    state.broadcast()
    await expect(page.locator('.own-seat .inline-result')).toHaveCount(2)
    if (width! >= 768 && height! > 550) {
      const boxes = await page.locator('.opponent-seats .player-seat').evaluateAll((seats) =>
        seats.map((seat) => {
          const b = seat.getBoundingClientRect()
          return { x: b.x, y: b.y, width: b.width, height: b.height }
        }),
      )
      for (let i = 0; i < boxes.length; i++)
        for (let j = i + 1; j < boxes.length; j++) {
          const a = boxes[i]!,
            b = boxes[j]!
          if (Math.abs(a.x - b.x) < 10)
            expect(Math.max(a.y, b.y)).toBeGreaterThanOrEqual(
              Math.min(a.y + a.height, b.y + b.height),
            )
        }
    }
    await page.screenshot({ path: `test-results/polish-results-${width}.png` })
  })
}
