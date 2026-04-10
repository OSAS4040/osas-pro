/**
 * تخزين مؤقت لمحتوى المرفقات (Data URL) في الجلسة فقط — لا يُرسل للخادم.
 * يُستخدم لعرض/تنزيل الملف من شاشة الأرشفة بعد إضافة مستند من «مستندات المنشأة» في نفس الجلسة.
 */
const KEY = 'company_documents_session_blobs_v1'

function readMap(): Record<string, string> {
  try {
    const raw = sessionStorage.getItem(KEY)
    if (!raw) return {}
    const o = JSON.parse(raw) as unknown
    return o && typeof o === 'object' && !Array.isArray(o) ? (o as Record<string, string>) : {}
  } catch {
    return {}
  }
}

function writeMap(m: Record<string, string>): void {
  try {
    sessionStorage.setItem(KEY, JSON.stringify(m))
  } catch {
    /* تجاهل تجاوز الحصة أو وضع خاص */
  }
}

export function getSessionDocBlob(docId: string): string {
  if (!docId) return ''
  const v = readMap()[docId]
  return typeof v === 'string' && v.startsWith('data:') ? v : ''
}

export function setSessionDocBlob(docId: string, dataUrl: string): void {
  if (!docId || !dataUrl || !dataUrl.startsWith('data:')) return
  const m = readMap()
  m[docId] = dataUrl
  writeMap(m)
}

export function removeSessionDocBlob(docId: string): void {
  if (!docId) return
  const m = readMap()
  if (docId in m) {
    delete m[docId]
    writeMap(m)
  }
}
