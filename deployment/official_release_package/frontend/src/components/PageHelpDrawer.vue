<template>
  <div v-if="entry">
    <button
      type="button"
      class="fixed bottom-28 left-6 z-[9991] flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition-colors dark:bg-primary-500 dark:hover:bg-primary-600"
      title="دليل المستخدم لهذه الصفحة"
      @click="open = !open"
    >
      <BookOpenIcon class="w-5 h-5" />
      <span class="hidden sm:inline">دليل الصفحة</span>
    </button>

    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="open"
          class="fixed inset-0 z-[9993] flex justify-start items-end sm:items-stretch pointer-events-none"
        >
          <div class="absolute inset-0 bg-black/40 pointer-events-auto" @click="open = false" />
          <aside
            class="relative pointer-events-auto m-4 sm:m-0 sm:mr-auto w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-2xl"
            dir="rtl"
          >
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
              <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ entry.title }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  إصدار {{ entry.version }} · {{ entry.updatedAt }}
                </p>
              </div>
              <button type="button" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700" @click="open = false">
                <XMarkIcon class="w-5 h-5 text-slate-500" />
              </button>
            </div>
            <div class="p-4 overflow-y-auto flex-1 space-y-4 text-sm text-slate-700 dark:text-slate-300">
              <p class="text-slate-600 dark:text-slate-400 leading-relaxed">{{ entry.summary }}</p>
              <section v-for="(s, i) in entry.sections" :key="i" class="rounded-xl bg-slate-50 dark:bg-slate-900/60 p-3 border border-slate-100 dark:border-slate-700">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ s.title }}</h3>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">{{ s.body }}</p>
              </section>
            </div>
            <div class="p-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-2">
              <button
                type="button"
                class="flex-1 min-w-[120px] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-600"
                @click="copyMarkdown"
              >
                <ClipboardDocumentIcon class="w-4 h-4" />
                نسخ نصي
              </button>
              <button
                type="button"
                class="flex-1 min-w-[120px] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg bg-primary-600 text-white text-xs font-medium hover:bg-primary-700"
                @click="shareHelp"
              >
                <ShareIcon class="w-4 h-4" />
                مشاركة
              </button>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { BookOpenIcon, XMarkIcon, ClipboardDocumentIcon, ShareIcon } from '@heroicons/vue/24/outline'
import { getPageHelp, PAGE_HELP_VERSION, type PageHelpEntry } from '@/help/pageHelpRegistry'

const route = useRoute()
const open = ref(false)

const entry = computed<PageHelpEntry | null>(() => getPageHelp(route.name as string, route.path))

watch(
  () => route.fullPath,
  () => {
    open.value = false
  },
)

function toMarkdown(e: PageHelpEntry): string {
  let md = `# ${e.title}\n\n_${e.summary}_\n\n**إصدار:** ${e.version} · **تحديث:** ${e.updatedAt}\n\n`
  e.sections.forEach((s) => {
    md += `## ${s.title}\n\n${s.body}\n\n`
  })
  md += `\n---\nOsas Pro · مساعد الصفحات v${PAGE_HELP_VERSION}\n`
  return md
}

async function copyMarkdown() {
  if (!entry.value) return
  try {
    await navigator.clipboard.writeText(toMarkdown(entry.value))
  } catch {
    /* ignore */
  }
}

async function shareHelp() {
  if (!entry.value) return
  const text = toMarkdown(entry.value)
  const url = typeof window !== 'undefined' ? window.location.href : ''
  if (navigator.share) {
    try {
      await navigator.share({ title: entry.value.title, text: entry.value.summary, url })
      return
    } catch {
      /* fall through */
    }
  }
  try {
    await navigator.clipboard.writeText(`${text}\n${url}`)
  } catch {
    /* ignore */
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
