import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.join(__dirname, '..')
const vuePath = path.join(root, 'src/views/platform/PlatformAdminDashboardPage.vue')
const outPath = path.join(root, 'src/composables/platform/_extracted_script_utf8.txt')

const c = fs.readFileSync(vuePath, 'utf8')
const m = c.match(/<script setup lang="ts">([\s\S]*?)<\/script>/)
if (!m) throw new Error('no script block')
fs.mkdirSync(path.dirname(outPath), { recursive: true })
fs.writeFileSync(outPath, m[1], 'utf8')
console.log('written', outPath, 'chars', m[1].length)
