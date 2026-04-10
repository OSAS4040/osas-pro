let depth = 0

export function lockBodyScroll(): void {
  if (typeof document === 'undefined') return
  depth++
  if (depth === 1) document.body.style.overflow = 'hidden'
}

export function unlockBodyScroll(): void {
  if (typeof document === 'undefined') return
  depth = Math.max(0, depth - 1)
  if (depth === 0) document.body.style.overflow = ''
}
