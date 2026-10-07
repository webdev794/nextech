// Client-side pre-check for product photos, mirroring the server-side rule in
// MediaController::store() (folder=products): JPEG/PNG, max 3 MB, at least
// 400x400px, square (1:1). Catches the common mistake instantly instead of
// waiting on a round trip; the server rule is still the real, authoritative gate.
const MAX_BYTES = 3 * 1024 * 1024
const MIN_DIMENSION = 400

export function checkProductImage(file) {
  return new Promise((resolve) => {
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
      resolve('Product photos must be JPEG or PNG.')
      return
    }
    if (file.size > MAX_BYTES) {
      resolve(`Product photos must be 3 MB or smaller (this one is ${(file.size / 1048576).toFixed(1)} MB).`)
      return
    }

    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      if (img.width < MIN_DIMENSION || img.height < MIN_DIMENSION) {
        resolve(`Product photos must be at least ${MIN_DIMENSION}×${MIN_DIMENSION}px (this one is ${img.width}×${img.height}).`)
      } else if (img.width !== img.height) {
        resolve(`Product photos must be square (1:1) — this one is ${img.width}×${img.height}.`)
      } else {
        resolve(null)
      }
    }
    img.onerror = () => { URL.revokeObjectURL(url); resolve('Could not read that image file.') }
    img.src = url
  })
}
