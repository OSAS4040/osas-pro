/**
 * يحوّل SVG المعرض في public/landing/showcase/*.svg إلى WebP (نفس الاسم الأساسي).
 * يُحسّن حجم التحميل على صفحة الهبوط مع طبقة احتياط SVG في المتصفح.
 * تشغيل: npm run generate:showcase
 */
import { readdir, readFile } from 'node:fs/promises'
import { dirname, join, extname, basename } from 'node:path'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const __dirname = dirname(fileURLToPath(import.meta.url))
const showcaseDir = join(__dirname, '..', 'public', 'landing', 'showcase')

const files = await readdir(showcaseDir)
const svgNames = files.filter((f) => extname(f).toLowerCase() === '.svg')

if (svgNames.length === 0) {
  console.warn('No SVG files in', showcaseDir)
  process.exit(0)
}

for (const name of svgNames) {
  const svgPath = join(showcaseDir, name)
  const stem = basename(name, '.svg')
  const outPath = join(showcaseDir, `${stem}.webp`)
  const buf = await readFile(svgPath)
  await sharp(buf)
    .webp({ quality: 88, effort: 6 })
    .toFile(outPath)
  console.log('Wrote', outPath)
}
