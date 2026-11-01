export const AVATAR_SIDE = 256
export const MAX_SOURCE_BYTES = 8 * 1024 * 1024

export class AvatarImageError extends Error {
  reason: 'type' | 'size' | 'decode'

  constructor(reason: 'type' | 'size' | 'decode') {
    super(reason)
    this.reason = reason
  }
}

/**
 * Centre-crops the picked file to a square and re-encodes it as a small JPEG
 * data URL. Everything is done in the browser so the server only ever stores
 * a tiny, predictable image (and EXIF/metadata never leaves the device).
 */
export async function fileToAvatarDataUrl(file: File): Promise<string> {
  if (!file.type.startsWith('image/')) throw new AvatarImageError('type')
  if (file.size > MAX_SOURCE_BYTES) throw new AvatarImageError('size')

  let bitmap: ImageBitmap
  try {
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  } catch {
    throw new AvatarImageError('decode')
  }

  try {
    const side = Math.min(bitmap.width, bitmap.height)
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = AVATAR_SIDE
    const context = canvas.getContext('2d')
    if (!context) throw new AvatarImageError('decode')
    // JPEG has no alpha — paint a neutral background under transparent PNGs.
    context.fillStyle = '#26332c'
    context.fillRect(0, 0, AVATAR_SIDE, AVATAR_SIDE)
    context.imageSmoothingQuality = 'high'
    context.drawImage(
      bitmap,
      (bitmap.width - side) / 2,
      (bitmap.height - side) / 2,
      side,
      side,
      0,
      0,
      AVATAR_SIDE,
      AVATAR_SIDE,
    )
    return canvas.toDataURL('image/jpeg', 0.88)
  } finally {
    bitmap.close()
  }
}
