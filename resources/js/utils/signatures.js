const fallbackAspectRatio = 420 / 120

const loadImage = (source) => new Promise((resolve, reject) => {
  const image = new Image()
  image.onload = () => resolve(image)
  image.onerror = reject
  image.src = source
})

const pixelContainsInk = (pixels, offset) => {
  const alpha = pixels[offset + 3]
  if (alpha <= 12) return false

  return pixels[offset] < 245 || pixels[offset + 1] < 245 || pixels[offset + 2] < 245
}

export async function prepareSignatureForPdf(source) {
  if (!source || typeof document === 'undefined') {
    return { source, aspectRatio: fallbackAspectRatio }
  }

  try {
    const image = await loadImage(source)
    const canvas = document.createElement('canvas')
    canvas.width = image.naturalWidth || image.width
    canvas.height = image.naturalHeight || image.height
    const context = canvas.getContext('2d', { willReadFrequently: true })
    context.drawImage(image, 0, 0)

    const { data } = context.getImageData(0, 0, canvas.width, canvas.height)
    let minX = canvas.width
    let minY = canvas.height
    let maxX = -1
    let maxY = -1

    for (let y = 0; y < canvas.height; y += 1) {
      for (let x = 0; x < canvas.width; x += 1) {
        const offset = ((y * canvas.width) + x) * 4
        if (!pixelContainsInk(data, offset)) continue

        minX = Math.min(minX, x)
        minY = Math.min(minY, y)
        maxX = Math.max(maxX, x)
        maxY = Math.max(maxY, y)
      }
    }

    if (maxX < minX || maxY < minY) {
      return { source, aspectRatio: canvas.width / canvas.height }
    }

    const padding = Math.max(6, Math.ceil(Math.max(maxX - minX, maxY - minY) * 0.04))
    const cropX = Math.max(0, minX - padding)
    const cropY = Math.max(0, minY - padding)
    const cropRight = Math.min(canvas.width - 1, maxX + padding)
    const cropBottom = Math.min(canvas.height - 1, maxY + padding)
    const cropWidth = cropRight - cropX + 1
    const cropHeight = cropBottom - cropY + 1
    const croppedCanvas = document.createElement('canvas')
    croppedCanvas.width = cropWidth
    croppedCanvas.height = cropHeight
    croppedCanvas.getContext('2d').drawImage(
      canvas,
      cropX,
      cropY,
      cropWidth,
      cropHeight,
      0,
      0,
      cropWidth,
      cropHeight
    )

    return {
      source: croppedCanvas.toDataURL('image/png'),
      aspectRatio: cropWidth / cropHeight,
    }
  } catch {
    return { source, aspectRatio: fallbackAspectRatio }
  }
}

export async function prepareSignaturesForPdf(signatures) {
  const entries = Object.entries(signatures || {}).filter(([, source]) => Boolean(source))
  const prepared = await Promise.all(entries.map(async ([key, source]) => [
    key,
    await prepareSignatureForPdf(source),
  ]))

  return Object.fromEntries(prepared)
}
