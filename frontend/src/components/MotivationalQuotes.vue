<template>
  <div class="overflow-hidden rounded-xl bg-gradient-to-l from-primary-700 to-primary-500 dark:from-slate-800 dark:to-slate-700 p-4 relative min-h-[72px]">
    <TransitionGroup name="quote">
      <div v-for="(q, i) in [quotes[current]]" :key="current"
        class="absolute inset-0 flex items-center gap-3 px-5">
        <span class="text-3xl flex-shrink-0 select-none">{{ q.emoji }}</span>
        <div>
          <p class="text-white font-semibold text-sm leading-snug">{{ q.text }}</p>
          <p class="text-primary-200 dark:text-slate-400 text-xs mt-0.5">— {{ q.author }}</p>
        </div>
      </div>
    </TransitionGroup>
    <!-- Dots -->
    <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-1">
      <button v-for="(_, i) in quotes" :key="i" @click="current = i"
        class="w-1.5 h-1.5 rounded-full transition-all"
        :class="i === current ? 'bg-white w-4' : 'bg-white/40'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useLocale } from '@/composables/useLocale'

const { lang } = useLocale()
const current = ref(0)
let timer: ReturnType<typeof setInterval>

const allQuotes: Record<string, { text: string; author: string; emoji: string }[]> = {
  ar: [
    { text: 'النجاح ليس نهاية المطاف، والفشل ليس قاتلاً — الشجاعة في الاستمرار هي ما يهم.', author: 'ونستون تشرشل', emoji: '🔥' },
    { text: 'العمل الجيد يُكلّم نفسه بنفسه.', author: 'مثل عربي', emoji: '⭐' },
    { text: 'كل يوم فرصة جديدة لتكون أفضل مما كنت عليه أمس.', author: 'مجهول', emoji: '🌅' },
    { text: 'الخدمة المتميزة تصنع عملاء مدى الحياة.', author: 'WorkshopOS', emoji: '🤝' },
    { text: 'دقيقة واحدة من التخطيط توفر ساعة من التنفيذ.', author: 'بنجامين فرانكلين', emoji: '📋' },
    { text: 'التفاصيل الصغيرة هي التي تصنع الفارق الكبير.', author: 'مجهول', emoji: '🎯' },
  ],
  en: [
    { text: "Success is not final, failure is not fatal — it's the courage to continue that counts.", author: 'Churchill', emoji: '🔥' },
    { text: 'Quality is not an act, it is a habit.', author: 'Aristotle', emoji: '⭐' },
    { text: 'Every morning brings new potential, but only if you make the most of it.', author: 'Unknown', emoji: '🌅' },
    { text: 'Customer service is not a department, it\'s an attitude.', author: 'Unknown', emoji: '🤝' },
    { text: 'An hour of planning saves ten hours of doing.', author: 'Unknown', emoji: '📋' },
    { text: 'Attention to detail is what separates the good from the great.', author: 'WorkshopOS', emoji: '🎯' },
  ],
  ur: [
    { text: 'کامیابی ایک سفر ہے، منزل نہیں۔', author: 'مجہول', emoji: '🔥' },
    { text: 'اچھا کام خود بولتا ہے۔', author: 'پاکستانی محاورہ', emoji: '⭐' },
    { text: 'ہر نیا دن ایک نئی شروعات ہے۔', author: 'مجہول', emoji: '🌅' },
    { text: 'محنت کا کوئی متبادل نہیں۔', author: 'مجہول', emoji: '💪' },
  ],
  bn: [
    { text: 'সফলতা একটি যাত্রা, গন্তব্য নয়।', author: 'অজানা', emoji: '🔥' },
    { text: 'পরিশ্রমের কোনো বিকল্প নেই।', author: 'অজানা', emoji: '💪' },
    { text: 'প্রতিটি নতুন দিন একটি নতুন সুযোগ।', author: 'অজানা', emoji: '🌅' },
  ],
  tl: [
    { text: 'Ang tagumpay ay isang paglalakbay, hindi isang destinasyon.', author: 'Hindi Kilala', emoji: '🔥' },
    { text: 'Walang kapalit ang pagsusumikap.', author: 'Filipino Salawikain', emoji: '💪' },
    { text: 'Bawat bagong araw ay isang bagong pagkakataon.', author: 'Hindi Kilala', emoji: '🌅' },
  ],
  hi: [
    { text: 'सफलता एक यात्रा है, मंजिल नहीं।', author: 'अज्ञात', emoji: '🔥' },
    { text: 'मेहनत का कोई विकल्प नहीं।', author: 'अज्ञात', emoji: '💪' },
    { text: 'हर नया दिन एक नया अवसर है।', author: 'अज्ञात', emoji: '🌅' },
    { text: 'गुणवत्ता की कोई कीमत नहीं होती।', author: 'अज्ञात', emoji: '⭐' },
  ],
}

const quotes = ref(allQuotes[lang.value] ?? allQuotes['ar'])

function next() {
  current.value = (current.value + 1) % quotes.value.length
}

onMounted(() => {
  current.value = Math.floor(Math.random() * quotes.value.length)
  timer = setInterval(next, 8000)
})
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.quote-enter-active  { transition: all 0.6s ease; }
.quote-leave-active  { transition: all 0.4s ease; position: absolute; }
.quote-enter-from    { opacity: 0; transform: translateX(30px); }
.quote-leave-to      { opacity: 0; transform: translateX(-30px); }
</style>
