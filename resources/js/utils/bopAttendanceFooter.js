const footerImageSrc = '/img/bop/kooperationspartner.png'
const footerAspectRatio = 1544 / 255
const footerWidth = 141.7
const footerBottom = 6

let footerImagePromise = null

export const loadBopAttendanceFooterImage = () => {
  if (!footerImagePromise) {
    footerImagePromise = new Promise((resolve, reject) => {
      const image = new Image()
      image.onload = () => resolve(image)
      image.onerror = () => reject(new Error('Die Grafik der BOP-Kooperationspartner konnte nicht geladen werden.'))
      image.src = footerImageSrc
    })
  }

  return footerImagePromise
}

export const bopAttendanceFooterSpace = () => (footerWidth / footerAspectRatio) + footerBottom + 4

export const drawBopAttendanceFooter = (doc, footerImage) => {
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const width = Math.min(footerWidth, pageWidth - 20)
  const height = width / footerAspectRatio
  const x = (pageWidth - width) / 2
  const y = pageHeight - height - footerBottom

  doc.addImage(footerImage, 'PNG', x, y, width, height)
  doc.setTextColor(0, 0, 0)
}
