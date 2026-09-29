import { test, expect, type Page } from '@playwright/test'
import type { MatchData } from '../src/types/match'

// Opt-in: creates two disposable users and a private lobby on the local API.
test('live API: two players register, join, ready, play, advance and finish', async ({
  browser,
}) => {
  test.skip(process.env.RUN_LIVE_UI !== '1', 'Set RUN_LIVE_UI=1 to create local test accounts.')
  test.setTimeout(90000)
  const baseURL = process.env.UI_BASE_URL || 'http://127.0.0.1:5175'
  const apiURL = process.env.LIVE_API_URL || 'http://localhost:8000'
  const stamp = Date.now().toString(36)
  const password = `UI-${stamp}-test-pass`
  const hostContext = await browser.newContext({ baseURL })
  const guestContext = await browser.newContext({ baseURL })
  const host = await hostContext.newPage()
  const guest = await guestContext.newPage()
  const errors: string[] = []
  for (const page of [host, guest]) page.on('pageerror', (error) => errors.push(error.message))
  async function register(page: Page, suffix: string) {
    await page.goto('/register')
    await page.getByLabel('Username', { exact: true }).fill(`ui_${stamp}_${suffix}`)
    await page.getByLabel('Email', { exact: true }).fill(`ui_${stamp}_${suffix}@example.test`)
    await page.getByLabel('Password', { exact: true }).fill(password)
    await page.getByLabel('Confirm password').fill(password)
    await page.getByRole('button', { name: 'Create account' }).click()
    await expect(page).toHaveURL(/dashboard/)
  }
  let matchId: string | undefined
  try {
    await register(host, 'a')
    await register(guest, 'b')
    await host.getByRole('switch', { name: 'Private lobby' }).check()
    await host.getByLabel('Lobby password').fill(password)
    await host.getByLabel('Max players').fill('2')
    await host.getByRole('button', { name: 'Create lobby', exact: true }).click()
    await expect(host).toHaveURL(/lobby\//)
    const code = new URL(host.url()).pathname.split('/').pop()!
    await guest.getByLabel('Lobby code').fill(code.toLowerCase())
    await guest.getByLabel('Password (if private)').fill(password)
    await guest.getByRole('button', { name: 'Join lobby', exact: true }).click()
    await expect(guest).toHaveURL(new RegExp(`lobby/${code}`))
    await host.getByRole('button', { name: 'Ready', exact: true }).click()
    await guest.getByRole('button', { name: 'Ready', exact: true }).click()
    await expect(host.getByRole('button', { name: 'Start game' })).toBeEnabled({ timeout: 10000 })
    await host.getByRole('button', { name: 'Start game' }).click()
    await expect(host).toHaveURL(/match\//)
    await expect(guest).toHaveURL(/match\//, { timeout: 10000 })
    matchId = new URL(host.url()).pathname.split('/').pop()
    const observed = new Set<string>()
    for (let round = 0; round < 3; round++) {
      for (let step = 0; step < 18; step++) {
        const hostToken = await host.evaluate(() => localStorage.getItem('poker.token'))
        const response = await host.request.get(`${apiURL}/api/matches/${matchId}`, {
          headers: { Authorization: `Bearer ${hostToken}`, Accept: 'application/json' },
        })
        expect(response.ok()).toBe(true)
        const state = (await response.json()) as MatchData
        if (state.game.phase === 'round_finished') break
        let active: Page | undefined
        for (const page of [host, guest])
          if (await page.getByRole('button', { name: 'Stand', exact: true }).isEnabled())
            active = page
        if (!active) {
          await expect
            .poll(
              async () =>
                (await host.getByRole('button', { name: 'Stand', exact: true }).isEnabled()) ||
                (await guest.getByRole('button', { name: 'Stand', exact: true }).isEnabled()),
              { timeout: 12000 },
            )
            .toBe(true)
          continue
        }
        let action = 'Stand'
        for (const candidate of ['Split', 'Double', 'Hit'])
          if (
            !observed.has(candidate) &&
            (await active.getByRole('button', { name: candidate, exact: true }).isEnabled())
          ) {
            action = candidate
            break
          }
        const pending = active.waitForResponse(
          (response) =>
            response.url().endsWith(`/actions/${action.toLowerCase()}`) &&
            response.request().method() === 'POST',
        )
        await active.getByRole('button', { name: action, exact: true }).click()
        expect((await pending).ok()).toBe(true)
        observed.add(action)
      }
      await expect(host.locator('.own-seat .inline-result').first()).toBeVisible({ timeout: 12000 })
      await expect(host.getByRole('dialog')).not.toBeVisible()
      if (round < 2) {
        await host.getByRole('button', { name: 'Confirm bet', exact: true }).click()
        await expect(host.getByText('Bet confirmed:', { exact: false })).toBeVisible()
        await guest.getByRole('button', { name: 'Increase bet', exact: true }).click()
        await guest.getByRole('button', { name: 'Confirm bet', exact: true }).click()
        await expect(host.getByText(`Round ${round + 2}`, { exact: true })).toBeVisible({
          timeout: 12000,
        })
        await expect(guest.getByText(`Round ${round + 2}`, { exact: true })).toBeVisible({
          timeout: 12000,
        })
      }
    }
    expect(observed.has('Stand')).toBe(true)
    expect(observed.has('Hit')).toBe(true)
    expect(observed.has('Double')).toBe(true)
    console.log('Live actions observed:', [...observed].join(', '))
    await host.getByRole('button', { name: 'Game settings' }).click()
    await host.getByRole('dialog').getByRole('button', { name: 'Finish match' }).click()
    await host.getByRole('dialog').getByRole('button', { name: 'Finish match' }).click()
    await expect(host.locator('.action-status')).toHaveText('Match complete')
    await expect(guest.locator('.action-status')).toHaveText('Match complete', { timeout: 10000 })
    await host.getByRole('button', { name: 'Leave', exact: true }).click()
    await host.getByRole('dialog').getByRole('button', { name: 'Leave table' }).click()
    await expect(host).toHaveURL(/dashboard/)
    await host.goto('/profile')
    await host.getByRole('button', { name: 'Log out', exact: true }).last().click()
    await host.getByRole('dialog').getByRole('button', { name: 'Log out' }).click()
    await expect(host).toHaveURL(/login/)
    await host.getByLabel('Email').fill(`ui_${stamp}_a@example.test`)
    await host.getByLabel('Password').fill(password)
    await host.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(host).toHaveURL(/dashboard/)
    expect(errors).toEqual([])
  } finally {
    if (matchId) {
      const token = await host.evaluate(() => localStorage.getItem('poker.token')).catch(() => null)
      if (token)
        await host.request
          .post(`${apiURL}/api/matches/${matchId}/finish`, {
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
          })
          .catch(() => undefined)
    }
    await hostContext.close()
    await guestContext.close()
  }
})
