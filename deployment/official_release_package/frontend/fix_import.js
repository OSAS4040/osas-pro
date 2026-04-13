const content = require('fs').readFileSync('/app/src/components/support/NewTicketModal.vue', 'utf8')
const fixed = content.replace("import { ref, watch } from 'vue'", "import { ref, watch, watchEffect } from 'vue'")
require('fs').writeFileSync('/app/src/components/support/NewTicketModal.vue', fixed)
console.log('done')
