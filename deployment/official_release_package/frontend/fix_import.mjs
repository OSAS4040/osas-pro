import { readFileSync, writeFileSync } from 'fs'
const content = readFileSync('/app/src/components/support/NewTicketModal.vue', 'utf8')
const fixed = content.replace("import { ref, watch } from 'vue'", "import { ref, watch, watchEffect } from 'vue'")
writeFileSync('/app/src/components/support/NewTicketModal.vue', fixed)
console.log('done')
