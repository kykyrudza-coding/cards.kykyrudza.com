import { test, expect } from '@playwright/test'

// Fixtures only intercept the test browser. They are never imported by the app.
const PNG_1X1 = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64',
)

test('uploading and removing an avatar updates the account everywhere', async ({ page }) => {
  const user = { id: 1, username: 'Ilya', email: 'ui@example.test', avatar: null as string | null }
  const bodies: { image?: string }[] = []
  await page.addInitScript(() => {
    localStorage.setItem('poker.locale', 'en')
    localStorage.setItem('poker.token', 'fixture-token')
  })
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (path === '/api/auth/me') return route.fulfill({ json: user })
    if (path === '/api/profile/avatar' && request.method() === 'POST') {
      bodies.push(request.postDataJSON())
      user.avatar = '/api/users/1/avatar?v=1'
      return route.fulfill({ json: user })
    }
    if (path === '/api/profile/avatar' && request.method() === 'DELETE') {
      user.avatar = null
      return route.fulfill({ json: user })
    }
    if (path === '/api/users/1/avatar')
      return route.fulfill({ body: PNG_1X1, contentType: 'image/png' })
    return route.fulfill({ status: 404, json: { message: 'Not found' } })
  })

  await page.goto('/settings')
  const editor = page.locator('.avatar-editor')
  await expect(editor.locator('.avatar img')).toHaveCount(0)

  await editor.locator('input[type=file]').setInputFiles({
    name: 'me.png',
    mimeType: 'image/png',
    buffer: PNG_1X1,
  })

  await expect(editor.locator('.avatar img')).toHaveCount(1)
  expect(bodies).toHaveLength(1)
  // Cropped client-side to a small square JPEG data URL.
  expect(bodies[0]!.image).toMatch(/^data:image\/jpeg;base64,/)
  expect(bodies[0]!.image!.length).toBeLessThan(262144)

  // The same avatar shows in the sidebar user block.
  await expect(page.locator('.sidebar-user .avatar img, .topbar-right .avatar img').first()).toBeVisible()
  const loaded = await editor
    .locator('.avatar img')
    .evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)
  expect(loaded).toBe(true)

  // Non-images are rejected before any request is made.
  await editor.locator('input[type=file]').setInputFiles({
    name: 'notes.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('hello'),
  })
  await expect(editor.getByRole('alert')).toContainText('not an image')
  expect(bodies).toHaveLength(1)

  await editor.getByRole('button', { name: 'Remove avatar' }).click()
  await expect(editor.locator('.avatar img')).toHaveCount(0)
})
