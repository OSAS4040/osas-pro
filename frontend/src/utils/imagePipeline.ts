/**
 * معالجة صور قبل الرفع أو إرسال base64 للـ API: تقليل الحجم والبعد الأقصى
 * يحسّن زمن الرفع وثبات OCR دون الاعتماد على امتدادات خادم إضافية.
 */

const DEFAULT_MAX_EDGE = Number(import.meta.env.VITE_IMAGE_UPLOAD_MAX_EDGE) || 1600
const DEFAULT_JPEG_QUALITY = Number(import.meta.env.VITE_IMAGE_UPLOAD_JPEG_QUALITY) || 0.88

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('image_load_failed'))
    img.src = src
  })
}

/**
 * يصغّر الصورة بحيث لا يتجاوز أطول ضلع `maxEdge` بكسل، ثم يعيد JPEG base64 data URL.
 */
export async function downscaleDataUrlForUpload(
  dataUrl: string,
  maxEdge: number = DEFAULT_MAX_EDGE,
  jpegQuality: number = DEFAULT_JPEG_QUALITY,
): Promise<string> {
  if (!dataUrl.startsWith('data:image/')) {
    return dataUrl
  }

  const img = await loadImage(dataUrl)
  const w = img.naturalWidth || img.width
  const h = img.naturalHeight || img.height
  if (w <= 0 || h <= 0) {
    return dataUrl
  }

  const longest = Math.max(w, h)
  if (longest <= maxEdge) {
    const c = document.createElement('canvas')
    c.width = w
    c.height = h
    const ctx = c.getContext('2d')
    if (!ctx) return dataUrl
    ctx.drawImage(img, 0, 0)
    return c.toDataURL('image/jpeg', jpegQuality)
  }

  const scale = maxEdge / longest
  const tw = Math.max(1, Math.round(w * scale))
  const th = Math.max(1, Math.round(h * scale))
  const canvas = document.createElement('canvas')
  canvas.width = tw
  canvas.height = th
  const ctx = canvas.getContext('2d')
  if (!ctx) return dataUrl
  ctx.imageSmoothingEnabled = true
  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(img, 0, 0, tw, th)
  return canvas.toDataURL('image/jpeg', jpegQuality)
}
