let workerConfigured = false

/** الصفحة الأولى من PDF كصورة JPEG (لـ OCR على الخادم كصورة). يحمّل pdfjs عند أول استدعاء فقط. */
export async function convertPdfFileToJpegFile(pdfFile: File, scale = 2): Promise<File> {
  const pdfjsLib = await import('pdfjs-dist')
  if (!workerConfigured) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
      'pdfjs-dist/build/pdf.worker.min.mjs',
      import.meta.url,
    ).toString()
    workerConfigured = true
  }
  const data = new Uint8Array(await pdfFile.arrayBuffer())
  const pdf = await pdfjsLib.getDocument({ data }).promise
  const page = await pdf.getPage(1)
  const viewport = page.getViewport({ scale })
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('تعذّر إنشاء سياق الرسم')
  }
  canvas.width = viewport.width
  canvas.height = viewport.height
  const task = page.render({ canvasContext: ctx, viewport })
  await task.promise
  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (b) => (b ? resolve(b) : reject(new Error('فشل تحويل الصفحة إلى صورة'))),
      'image/jpeg',
      0.92,
    )
  })
  const baseName = pdfFile.name.replace(/\.pdf$/i, '') || 'invoice'
  return new File([blob], `${baseName}-p1.jpg`, { type: 'image/jpeg' })
}

export function isPdfFile(file: File): boolean {
  const t = (file.type || '').toLowerCase()
  if (t === 'application/pdf') return true
  return file.name.toLowerCase().endsWith('.pdf')
}
