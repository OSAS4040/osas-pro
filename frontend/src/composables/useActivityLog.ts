const STORAGE_KEY = 'workshopos_activity_log_v1'
const MAX = 250

export type ActivityEntry = {
  at: string
  action: string
  path: string
  detail?: string
}

function read(): ActivityEntry[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const arr = JSON.parse(raw)
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

export function logActivity(action: string, detail?: string): void {
  try {
    const path = typeof window !== 'undefined' ? window.location.pathname : ''
    const list = read()
    list.unshift({
      at: new Date().toISOString(),
      action,
      path,
      detail,
    })
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX)))
  } catch {
    /* ignore quota */
  }
}

export function getActivityLog(): ActivityEntry[] {
  return read()
}

export function clearActivityLog(): void {
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    /* */
  }
}
