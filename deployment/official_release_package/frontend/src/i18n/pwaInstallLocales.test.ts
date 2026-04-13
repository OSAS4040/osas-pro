import { describe, it, expect } from 'vitest'
import ar from './ar'
import en from './en'
import ur from './ur'
import hi from './hi'
import tl from './tl'
import bn from './bn'

const REQUIRED_PWA_KEYS = [
  'summaryTitle',
  'tapForHelp',
  'hide',
  'introPart1',
  'introStrong',
  'introPart2',
  'copyLink',
  'copied',
  'installPrompt',
  'iphoneLabel',
  'iphoneHint',
  'androidLabel',
  'androidHint',
] as const

const locales = { ar, en, ur, hi, tl, bn }

describe('pwaInstall locale strings', () => {
  for (const [code, messages] of Object.entries(locales)) {
    it(`has all required keys for ${code}`, () => {
      const block = (messages as Record<string, unknown>).pwaInstall
      expect(block, `${code}: missing pwaInstall`).toBeTruthy()
      expect(typeof block).toBe('object')
      const o = block as Record<string, unknown>
      for (const k of REQUIRED_PWA_KEYS) {
        expect(typeof o[k], `${code}.pwaInstall.${k}`).toBe('string')
        expect((o[k] as string).length, `${code}.pwaInstall.${k} empty`).toBeGreaterThan(0)
      }
    })
  }
})
