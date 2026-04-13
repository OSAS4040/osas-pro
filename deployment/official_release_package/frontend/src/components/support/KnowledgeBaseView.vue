<template>
  <div dir="rtl">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <BookOpenIcon class="w-6 h-6 text-indigo-500" />
        قاعدة المعرفة
      </h2>
      <div class="flex gap-2">
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                @click="showNewArticle = true"
        >
          + مقال جديد
        </button>
      </div>
    </div>

    <!-- Search -->
    <div class="relative mb-6">
      <MagnifyingGlassIcon class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
      <input v-model="search" placeholder="ابحث في قاعدة المعرفة..." class="w-full border border-gray-300 dark:border-gray-600 rounded-xl pr-10 pl-4 py-3 text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none"
             @input="fetchArticles"
      />
    </div>

    <!-- Categories -->
    <div class="flex gap-2 flex-wrap mb-6">
      <button :class="!selectedCat ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700'"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
              @click="selectedCat = ''; fetchArticles()"
      >
        الكل
      </button>
      <button v-for="c in categories" :key="c.id"
              :class="selectedCat === c.id ? 'text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700'"
              :style="selectedCat === c.id ? { backgroundColor: c.color } : {}"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
              @click="selectedCat = c.id; fetchArticles()"
      >
        {{ c.name }} ({{ c.articles_count }})
      </button>
    </div>

    <!-- Featured Articles -->
    <div v-if="!search && !selectedCat" class="mb-6">
      <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">⭐ المقالات المميزة</h3>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="a in featured" :key="a.id" class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 border border-indigo-100 dark:border-indigo-800 rounded-xl p-4 cursor-pointer hover:shadow-md transition-all"
             @click="openArticle(a)"
        >
          <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-1">{{ a.title }}</h4>
          <p class="text-xs text-gray-500 line-clamp-2">{{ a.summary }}</p>
          <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
            <span>👁 {{ a.views }}</span>
            <span>👍 {{ a.helpful_yes }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Articles List -->
    <div class="space-y-3">
      <div v-for="a in articles" :key="a.id" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition-all"
           @click="openArticle(a)"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span v-if="a.category" :style="{ color: a.category.color }" class="text-xs font-medium">{{ a.category.name }}</span>
              <span v-if="a.is_featured" class="text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded">مميز</span>
            </div>
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ a.title }}</h4>
            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ a.summary }}</p>
          </div>
          <div class="text-center flex-shrink-0">
            <div class="text-2xl font-bold text-indigo-500">{{ a.helpful_yes }}</div>
            <div class="text-xs text-gray-400">مفيد</div>
          </div>
        </div>
        <div class="flex items-center gap-4 mt-3 text-xs text-gray-400">
          <span>👁 {{ a.views }} مشاهدة</span>
          <span>✅ {{ a.helpful_yes }} | ❌ {{ a.helpful_no }}</span>
        </div>
      </div>
    </div>

    <!-- Article Detail Modal -->
    <Teleport to="body">
      <div v-if="selectedArticle" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" dir="rtl">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
          <div class="flex items-start justify-between p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-bold text-gray-900 dark:text-white text-lg flex-1">{{ selectedArticle.title }}</h3>
            <button class="text-gray-400 hover:text-gray-600" @click="selectedArticle = null">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <!-- eslint-disable-next-line vue/no-v-html -- محتوى مقالات قاعدة المعرفة (مصدر داخلي موثوق) -->
          <div class="flex-1 overflow-y-auto p-5 prose dark:prose-invert prose-sm max-w-none" v-html="selectedArticle.content"></div>
          <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <span class="text-sm text-gray-500">هل كان هذا المقال مفيدًا؟</span>
            <div class="flex gap-2">
              <button class="px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm font-medium transition-all" @click="vote(selectedArticle.id, true)">
                👍 نعم
              </button>
              <button class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm font-medium transition-all" @click="vote(selectedArticle.id, false)">
                👎 لا
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- New Article Modal -->
    <Teleport to="body">
      <div v-if="showNewArticle" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" dir="rtl" @click.self="showNewArticle = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl">
          <div class="flex items-center justify-between p-5 border-b">
            <h3 class="font-bold text-gray-900 dark:text-white">مقال جديد</h3>
            <button @click="showNewArticle = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
          </div>
          <form class="p-5 space-y-4" @submit.prevent="createArticle">
            <input v-model="newArticle.title" required placeholder="عنوان المقال" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
            <input v-model="newArticle.summary" placeholder="ملخص قصير" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
            <textarea v-model="newArticle.content" rows="5" placeholder="محتوى المقال (HTML مدعوم)" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white resize-none outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            <select v-model="newArticle.kb_category_id" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
              <option value="">اختر الفئة</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="flex items-center gap-4">
              <label class="flex items-center gap-2 text-sm">
                <input v-model="newArticle.is_featured" type="checkbox" class="rounded" /> مميز
              </label>
              <select v-model="newArticle.status" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 text-sm dark:bg-gray-700 dark:text-white">
                <option value="draft">مسودة</option>
                <option value="published">منشور</option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showNewArticle = false">إلغاء</button>
              <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">حفظ</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { BookOpenIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const articles        = ref<any[]>([])
const featured        = ref<any[]>([])
const categories      = ref<any[]>([])
const search          = ref('')
const selectedCat     = ref<any>('')
const selectedArticle = ref<any>(null)
const showNewArticle  = ref(false)
const newArticle      = ref({ title: '', summary: '', content: '', kb_category_id: '', is_featured: false, status: 'published' })

async function fetchArticles() {
  const params: any = { per_page: 30 }
  if (search.value)     params.search      = search.value
  if (selectedCat.value) params.category_id = selectedCat.value
  const res = await axios.get('/api/v1/support/kb', { params })
  articles.value = res.data.data?.data ?? res.data.data ?? []
  if (!search.value && !selectedCat.value) featured.value = articles.value.filter((a: any) => a.is_featured)
}

async function fetchCategories() {
  const res = await axios.get('/api/v1/support/kb-categories')
  categories.value = res.data.data ?? []
}

async function vote(id: number, helpful: boolean) {
  await axios.post(`/api/v1/support/kb/${id}/vote`, { helpful })
  await fetchArticles()
  selectedArticle.value = null
}

async function createArticle() {
  await axios.post('/api/v1/support/kb', newArticle.value)
  showNewArticle.value = false
  newArticle.value = { title: '', summary: '', content: '', kb_category_id: '', is_featured: false, status: 'published' }
  fetchArticles()
}

function openArticle(a: any) { selectedArticle.value = a }

onMounted(() => { fetchArticles(); fetchCategories() })
</script>
