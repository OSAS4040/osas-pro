<template>
  <div class="min-h-screen bg-gradient-to-br from-teal-800 to-emerald-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <TruckIcon class="w-9 h-9 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-white">بوابة إدارة الأسطول</h1>
        <p class="text-teal-200 text-sm mt-1">Fleet Management Portal</p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-8">
        <form class="space-y-5" @submit.prevent="handleLogin">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input v-model="form.email" type="email" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input v-model="form.password" type="password" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
            />
          </div>

          <p v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>

          <button type="submit" :disabled="loading"
                  class="w-full bg-teal-700 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-teal-800 transition-colors disabled:opacity-50"
          >
            {{ loading ? 'جارٍ تسجيل الدخول...' : 'دخول بوابة الأسطول' }}
          </button>
        </form>

        <div class="mt-6 pt-5 border-t border-gray-100 text-center">
          <RouterLink to="/login" class="text-xs text-gray-500 hover:text-blue-700 transition-colors">
            دخول الموظفين ←
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { TruckIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref('')

async function handleLogin(): Promise<void> {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.email, form.value.password)
    await router.push('/fleet-portal')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'فشل تسجيل الدخول'
  } finally {
    loading.value = false
  }
}
</script>
