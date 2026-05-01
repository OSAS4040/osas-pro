<template>
  <div class="space-y-6" dir="rtl">
    <NavigationSourceHint />
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100">التكاملات المتقدمة</h2>
      <p class="text-sm text-gray-400 mt-0.5">ربط مركز الخدمة أو منفذ البيع بالأنظمة الخارجية — الكاميرات، التتبع، الواتساب، البريد</p>
    </div>

    <!-- ══════ Tab Navigation ══════ -->
    <div class="flex gap-1 bg-gray-100 dark:bg-slate-800 p-1 rounded-xl overflow-x-auto">
      <button v-for="t in tabs" :key="t.id" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
              :class="activeTab === t.id ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700'"
              @click="onSelectTab(t.id)"
      >
        <component :is="t.icon" class="w-4 h-4" />
        {{ t.label }}
      </button>
    </div>

    <!-- ══════ WhatsApp Tab ══════ -->
    <section v-show="activeTab === 'whatsapp'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center">
            <ChatBubbleLeftEllipsisIcon class="w-5 h-5 text-green-600" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">واتساب</h3>
            <p class="text-xs text-gray-400">إرسال الفواتير وتأكيد المواعيد والإشعارات</p>
          </div>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="sub.hasFeature('work_orders') ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'"
        >
          {{ sub.hasFeature('work_orders') ? 'متاح' : 'يتطلب Professional' }}
        </span>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button v-for="p in waProviders" :key="p.id" :disabled="p.requiresPro && !sub.hasFeature('work_orders')"
                  class="flex items-start gap-3 p-3.5 rounded-xl border-2 text-right transition-all"
                  :class="[waSettings.provider === p.id ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300', p.requiresPro && !sub.hasFeature('work_orders') ? 'opacity-50 cursor-not-allowed' : '']"
                  @click="waSettings.provider = p.id"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-lg">{{ p.emoji }}</div>
            <div>
              <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ p.name }}</p>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ p.desc }}</p>
              <span v-if="p.badge" class="text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block" :class="p.badgeClass">{{ p.badge }}</span>
            </div>
          </button>
        </div>
        <template v-if="waSettings.provider === 'twilio'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label class="block text-xs text-gray-500 mb-1">Account SID</label><input v-model="waSettings.twilio_sid" class="field font-mono" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Auth Token</label><input v-model="waSettings.twilio_token" type="password" class="field font-mono" /></div>
            <div class="md:col-span-2"><label class="block text-xs text-gray-500 mb-1">رقم واتساب</label><input v-model="waSettings.twilio_from" class="field font-mono" placeholder="whatsapp:+14155238886" /></div>
          </div>
        </template>
        <template v-else-if="waSettings.provider === 'custom_api'">
          <div class="space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">API Endpoint</label><input v-model="waSettings.custom_api_url" class="field font-mono" placeholder="https://api.provider.com/send" /></div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="block text-xs text-gray-500 mb-1">API Key</label><input v-model="waSettings.custom_api_key" type="password" class="field font-mono" /></div>
              <div><label class="block text-xs text-gray-500 mb-1">رقم الإرسال</label><input v-model="waSettings.custom_from" class="field font-mono" /></div>
            </div>
          </div>
        </template>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-3">إرسال تلقائي عند:</label>
          <div class="space-y-2">
            <label v-for="t in waTriggers" :key="t.key" class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
              <div><p class="text-sm text-gray-800 dark:text-slate-200">{{ t.label }}</p><p class="text-xs text-gray-400">{{ t.desc }}</p></div>
              <button class="relative w-10 h-5 rounded-full transition-colors flex-shrink-0" :class="t.enabled ? 'bg-green-500' : 'bg-gray-200'" @click="t.enabled = !t.enabled">
                <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="t.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
              </button>
            </label>
          </div>
        </div>
        <div class="flex gap-3">
          <button :disabled="savingWA" class="px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50" @click="saveWA">{{ savingWA ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
          <button class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700" @click="testWA">إرسال تجريبي</button>
        </div>
      </div>
    </section>

    <!-- ══════ Email Tab ══════ -->
    <section v-show="activeTab === 'email'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center"><EnvelopeIcon class="w-5 h-5 text-blue-600" /></div>
        <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">البريد الإلكتروني</h3><p class="text-xs text-gray-400">إرسال الفواتير والإشعارات بالبريد</p></div>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button v-for="p in emailProviders" :key="p.id" class="flex items-start gap-3 p-3.5 rounded-xl border-2 text-right transition-all"
                  :class="emailSettings.provider === p.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600'"
                  @click="emailSettings.provider = p.id"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-lg">{{ p.emoji }}</div>
            <div><p class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ p.name }}</p><p class="text-xs text-gray-400 mt-0.5">{{ p.desc }}</p></div>
          </button>
        </div>
        <template v-if="emailSettings.provider === 'smtp'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label class="block text-xs text-gray-500 mb-1">SMTP Host</label><input v-model="emailSettings.host" class="field font-mono" placeholder="smtp.yourmail.com" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Port</label><input v-model="emailSettings.port" class="field font-mono" placeholder="587" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Username</label><input v-model="emailSettings.user" class="field" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">Password</label><input v-model="emailSettings.pass" type="password" class="field" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">من اسم</label><input v-model="emailSettings.from_name" class="field" placeholder="ورشتي للسيارات" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">من بريد</label><input v-model="emailSettings.from_email" class="field font-mono" placeholder="no-reply@workshop.sa" /></div>
          </div>
        </template>
        <template v-else-if="emailSettings.provider === 'sendgrid'">
          <div><label class="block text-xs text-gray-500 mb-1">SendGrid API Key</label><input v-model="emailSettings.sendgrid_key" type="password" class="field font-mono" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs text-gray-500 mb-1">من اسم</label><input v-model="emailSettings.from_name" class="field" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">من بريد</label><input v-model="emailSettings.from_email" class="field font-mono" /></div>
          </div>
        </template>
        <div class="flex gap-3">
          <button :disabled="savingEmail" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50" @click="saveEmail">{{ savingEmail ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
          <button class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700" @click="testEmail">إرسال تجريبي</button>
        </div>
      </div>
    </section>

    <!-- ══════ Cameras Tab ══════ -->
    <section v-show="activeTab === 'cameras'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center"><VideoCameraIcon class="w-5 h-5 text-primary-600" /></div>
          <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">كاميرات IP لمركز الخدمة / المنفذ</h3><p class="text-xs text-gray-400">ربط الكاميرات لمراقبة الرافعات ومدخل الاستقبال</p></div>
        </div>
        <button class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs text-white transition-colors hover:bg-primary-700" @click="addCamera">
          <PlusIcon class="w-3.5 h-3.5" /> إضافة كاميرا
        </button>
      </div>
      <div class="p-5 space-y-4">
        <!-- Usage Explanation -->
        <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 rounded-xl p-4 text-sm text-primary-800 dark:text-primary-300">
          <p class="font-medium mb-2">كيف يعمل نظام الكاميرات؟</p>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-primary-200 dark:bg-primary-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-primary-700 dark:text-primary-300">1</span><span>كاميرا الاستقبال تقرأ لوحة المركبة تلقائياً عند الدخول</span></div>
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-primary-200 dark:bg-primary-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-primary-700 dark:text-primary-300">2</span><span>كاميرا الرافعة تكتشف وجود مركبة وتُحدّث الحالة تلقائياً</span></div>
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-primary-200 dark:bg-primary-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-primary-700 dark:text-primary-300">3</span><span>تُلتقط صور تلقائياً عند الاستقبال والتسليم لحماية قانونية</span></div>
          </div>
        </div>

        <!-- Camera List -->
        <div v-for="(cam, i) in cameras" :key="i" class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full" :class="cam.active ? 'bg-green-500' : 'bg-gray-300'"></span>
              <input v-model="cam.name" class="text-sm font-medium text-gray-900 dark:text-slate-100 bg-transparent border-none outline-none focus:bg-gray-50 dark:focus:bg-slate-700 rounded px-1" placeholder="اسم الكاميرا" />
            </div>
            <div class="flex items-center gap-2">
              <button class="text-xs text-primary-600 hover:underline" @click="testCameraStream(i)">اختبار</button>
              <button class="text-xs text-red-500 hover:underline" @click="cameras.splice(i,1)">حذف</button>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">نوع الكاميرا</label>
              <select v-model="cam.type" class="field text-sm">
                <option value="reception">استقبال (قراءة لوحات)</option>
                <option value="bay">رافعة (كشف وجود)</option>
                <option value="general">عامة (مراقبة)</option>
                <option value="dashcam">داش كام</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">بروتوكول</label>
              <select v-model="cam.protocol" class="field text-sm">
                <option value="rtsp">RTSP</option>
                <option value="http">HTTP/MJPEG</option>
                <option value="onvif">ONVIF</option>
                <option value="hikvision">Hikvision API</option>
                <option value="dahua">Dahua API</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">الرافعة المرتبطة</label>
              <select v-model="cam.bay_id" class="field text-sm">
                <option value="">— بدون ربط —</option>
                <option v-for="b in bays" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">عنوان الكاميرا (URL)</label>
            <input v-model="cam.url" class="field font-mono text-xs" :placeholder="cam.protocol === 'rtsp' ? 'rtsp://192.168.1.100:554/stream1' : 'http://192.168.1.100/video'" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs text-gray-400 mb-1">اسم المستخدم</label><input v-model="cam.username" class="field" /></div>
            <div><label class="block text-xs text-gray-400 mb-1">كلمة المرور</label><input v-model="cam.password" type="password" class="field" /></div>
          </div>
          <!-- Live Preview Placeholder -->
          <div v-if="cam.previewActive" class="rounded-xl overflow-hidden bg-black aspect-video relative">
            <div class="absolute inset-0 flex items-center justify-center text-white text-sm">
              <div class="text-center">
                <VideoCameraIcon class="w-8 h-8 mx-auto mb-2 text-white/60" />
                <p>بث مباشر: {{ cam.name }}</p>
                <p class="text-xs text-white/40 font-mono mt-1">{{ cam.url }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!cameras.length" class="text-center py-8">
          <VideoCameraIcon class="w-12 h-12 text-gray-200 dark:text-slate-600 mx-auto mb-3" />
          <p class="text-sm text-gray-400">لا توجد كاميرات مضافة</p>
          <button class="mt-3 text-xs text-primary-600 hover:underline" @click="addCamera">+ إضافة أول كاميرا</button>
        </div>

        <button :disabled="savingCameras" class="rounded-lg bg-primary-600 px-5 py-2 text-sm text-white transition-colors hover:bg-primary-700 disabled:opacity-50" @click="saveCameras">
          {{ savingCameras ? 'جارٍ الحفظ...' : 'حفظ إعدادات الكاميرات' }}
        </button>
      </div>
    </section>

    <!-- ══════ Tracking Tab ══════ -->
    <section v-show="activeTab === 'tracking'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center"><MapPinIcon class="w-5 h-5 text-blue-600" /></div>
        <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">أنظمة تتبع المركبات</h3><p class="text-xs text-gray-400">ربط GPS والداش كام وأنظمة مراقبة الأسطول</p></div>
      </div>
      <div class="p-5 space-y-5">
        <!-- Tracking Providers -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-3">مزود نظام التتبع</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button v-for="p in trackingProviders" :key="p.id" class="flex flex-col items-center p-4 rounded-xl border-2 transition-all gap-2"
                    :class="trackingSettings.provider === p.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300'"
                    @click="trackingSettings.provider = p.id"
            >
              <span class="text-3xl">{{ p.emoji }}</span>
              <p class="text-xs font-semibold text-gray-800 dark:text-slate-200 text-center">{{ p.name }}</p>
              <p class="text-[10px] text-gray-400 text-center">{{ p.desc }}</p>
            </button>
          </div>
        </div>

        <!-- Provider Config -->
        <div v-if="trackingSettings.provider !== 'none'" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">API Key / Token</label>
              <input v-model="trackingSettings.api_key" type="password" class="field font-mono" placeholder="••••••••••" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">رابط لوحة التحكم</label>
              <input v-model="trackingSettings.dashboard_url" class="field font-mono" placeholder="https://track.provider.com" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">تعليمات الربط لكل مركبة</label>
            <p class="text-xs text-gray-400 bg-gray-50 dark:bg-slate-700 rounded-lg p-3">
              أدخل معرّف المركبة من نظام التتبع في صفحة تفاصيل كل مركبة (حقل "معرّف التتبع").
              سيظهر زر "التتبع المباشر" تلقائياً في بطاقة المركبة وفي بوابة العميل.
            </p>
          </div>
        </div>

        <!-- Dashcam Section -->
        <div class="border-t border-gray-100 dark:border-slate-700 pt-5">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-slate-100">نظام الداش كام والفيديو</h4>
              <p class="text-xs text-gray-400">تسجيل وحفظ وعرض مقاطع الداش كام للعملاء</p>
            </div>
            <button class="relative w-10 h-5 rounded-full transition-colors" :class="dashcam.enabled ? 'bg-blue-500' : 'bg-gray-200'" @click="dashcam.enabled = !dashcam.enabled">
              <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="dashcam.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
            </button>
          </div>
          <div v-if="dashcam.enabled" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <button v-for="d in dashcamProviders" :key="d.id" class="flex items-center gap-2 p-3 rounded-xl border-2 transition-all"
                      :class="dashcam.provider === d.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600'"
                      @click="dashcam.provider = d.id"
              >
                <span class="text-xl">{{ d.emoji }}</span>
                <div class="text-right">
                  <p class="text-xs font-semibold text-gray-900 dark:text-slate-100">{{ d.name }}</p>
                  <p class="text-[10px] text-gray-400">{{ d.desc }}</p>
                </div>
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div><label class="block text-xs text-gray-500 mb-1">API Key للداش كام</label><input v-model="dashcam.api_key" type="password" class="field font-mono" /></div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">مدة الاحتفاظ بالفيديو</label>
                <select v-model="dashcam.retention_days" class="field">
                  <option value="7">7 أيام</option><option value="30">30 يوم</option><option value="90">90 يوم</option><option value="365">سنة</option>
                </select>
              </div>
            </div>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 dark:border-slate-700">
              <input v-model="dashcam.auto_capture_on_arrival" type="checkbox" class="rounded" />
              <div><p class="text-sm text-gray-800 dark:text-slate-200">التقاط تلقائي عند الاستقبال</p><p class="text-xs text-gray-400">يُرسَل للعميل رابط مشاهدة مقطع استقبال سيارته</p></div>
            </label>
          </div>
        </div>

        <button :disabled="savingTracking" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50" @click="saveTracking">
          {{ savingTracking ? 'جارٍ الحفظ...' : 'حفظ إعدادات التتبع' }}
        </button>
      </div>
    </section>

    <!-- ══════ Loyalty Tab ══════ -->
    <section v-show="activeTab === 'loyalty'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-yellow-100 flex items-center justify-center"><StarIcon class="w-5 h-5 text-yellow-600" /></div>
          <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">نظام الولاء</h3><p class="text-xs text-gray-400">نقاط المكافآت وإدارة برامج الولاء</p></div>
        </div>
        <button class="relative w-10 h-5 rounded-full transition-colors" :class="loyalty.enabled ? 'bg-yellow-500' : 'bg-gray-200'" @click="loyalty.enabled = !loyalty.enabled">
          <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="loyalty.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
        </button>
      </div>
      <div class="p-5 space-y-5">
        <!-- Internal Loyalty -->
        <div v-if="loyalty.enabled">
          <h4 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-3">إعدادات نقاط الولاء الداخلية</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-4">
              <label class="block text-xs text-gray-500 mb-2">نقطة لكل ريال منفق</label>
              <div class="flex items-center gap-2">
                <input v-model.number="loyalty.points_per_riyal" type="number" min="0.1" step="0.1" class="field text-center font-bold text-lg" />
                <span class="text-xs text-gray-400 whitespace-nowrap">نقطة / ريال</span>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-4">
              <label class="block text-xs text-gray-500 mb-2">قيمة النقطة عند الاسترداد</label>
              <div class="flex items-center gap-2">
                <input v-model.number="loyalty.point_value_in_halala" type="number" min="1" class="field text-center font-bold text-lg" />
                <span class="text-xs text-gray-400 whitespace-nowrap">هللة / نقطة</span>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-4">
              <label class="block text-xs text-gray-500 mb-2">حد أدنى للاسترداد</label>
              <div class="flex items-center gap-2">
                <input v-model.number="loyalty.min_redeem_points" type="number" min="100" class="field text-center font-bold text-lg" />
                <span class="text-xs text-gray-400 whitespace-nowrap">نقطة</span>
              </div>
            </div>
          </div>

          <!-- Tiers -->
          <div class="mt-4">
            <h5 class="text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase mb-3">مستويات الولاء</h5>
            <div class="space-y-2">
              <div v-for="tier in loyaltyTiers" :key="tier.name"
                   class="flex items-center gap-4 p-3 rounded-xl border border-gray-100 dark:border-slate-700"
              >
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg flex-shrink-0" :style="{ background: tier.color + '20', color: tier.color }">{{ tier.emoji }}</div>
                <div class="flex-1 grid grid-cols-3 gap-3">
                  <div><p class="text-xs text-gray-400">المستوى</p><p class="text-sm font-semibold" :style="{ color: tier.color }">{{ tier.name }}</p></div>
                  <div><p class="text-xs text-gray-400">من نقطة</p><input v-model.number="tier.from" type="number" class="field text-sm" /></div>
                  <div>
                    <p class="text-xs text-gray-400">مضاعف النقاط</p>
                    <div class="flex items-center gap-1"><input v-model.number="tier.multiplier" type="number" min="1" step="0.5" class="field text-sm" /><span class="text-xs text-gray-400">×</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- External Loyalty Integration -->
        <div class="border-t border-gray-100 dark:border-slate-700 pt-5">
          <h4 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-1">ربط مع نظام ولاء خارجي</h4>
          <p class="text-xs text-gray-400 mb-3">اجمع بين نظامك الداخلي ومنصات الولاء المتخصصة</p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button v-for="ext in externalLoyalty" :key="ext.id" class="flex flex-col items-center gap-2 p-3 rounded-xl border-2 transition-all text-center"
                    :class="loyalty.external === ext.id ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20' : 'border-gray-200 dark:border-slate-600'"
                    @click="loyalty.external = loyalty.external === ext.id ? '' : ext.id"
            >
              <span class="text-2xl">{{ ext.emoji }}</span>
              <p class="text-xs font-semibold text-gray-800 dark:text-slate-200">{{ ext.name }}</p>
              <p class="text-[10px] text-gray-400">{{ ext.desc }}</p>
            </button>
          </div>
          <div v-if="loyalty.external" class="mt-3 space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">API Key للنظام الخارجي</label><input v-model="loyalty.external_api_key" type="password" class="field font-mono" /></div>
            <div><label class="block text-xs text-gray-500 mb-1">معرّف البرنامج (Program ID)</label><input v-model="loyalty.external_program_id" class="field font-mono" /></div>
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl p-3 text-xs text-amber-800 dark:text-amber-300">
              عند تفعيل الربط، ستُرسَل نقاط الولاء تلقائياً للنظام الخارجي بعد كل معاملة مكتملة.
            </div>
          </div>
        </div>

        <button :disabled="savingLoyalty" class="px-5 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700 disabled:opacity-50" @click="saveLoyalty">
          {{ savingLoyalty ? 'جارٍ الحفظ...' : 'حفظ إعدادات الولاء' }}
        </button>
      </div>
    </section>

    <!-- ══════ Webhooks Tab ══════ -->
    <section v-show="activeTab === 'webhooks'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-slate-200 dark:bg-slate-600 flex items-center justify-center">
          <BoltIcon class="w-5 h-5 text-slate-700 dark:text-slate-200" />
        </div>
        <div>
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Webhooks صادرة</h3>
          <p class="text-xs text-gray-400">إشعار خادمك بأحداث المنشأة — يتطلب صلاحية إدارة Webhooks. السر يُعرض مرة واحدة فقط.</p>
        </div>
      </div>
      <div class="p-5 space-y-5">
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 dark:bg-amber-950/30 dark:border-amber-800 px-4 py-3 text-xs text-amber-950 dark:text-amber-100">
          <p class="font-semibold mb-1">أمان التوقيع</p>
          <p>خزّن التوقيع (Secret) في مدير أسرار أو متغيرات بيئة خادمك. لا تُضمّنه في الواجهة أو المستودع. التعطيل هنا يوقف الإرسال دون حذف السجل.</p>
        </div>

        <div v-if="webhookLoadError" class="text-sm text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg px-3 py-2">{{ webhookLoadError }}</div>

        <div class="space-y-3">
          <div v-for="ep in webhookEndpoints" :key="ep.id" class="rounded-xl border border-gray-200 dark:border-slate-600 p-4 space-y-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-gray-500">عنوان الاستقبال</p>
                <p class="text-sm font-mono break-all text-gray-900 dark:text-slate-100">{{ ep.url }}</p>
                <p class="text-[11px] text-gray-400 mt-1">الأحداث: {{ formatWebhookEvents(ep.events) }}</p>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full shrink-0" :class="ep.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600'">
                {{ ep.is_active ? 'نشط' : 'معطّل' }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700" @click="toggleDeliveries(ep.id)">
                {{ deliveriesForId === ep.id ? 'إخفاء التسليمات' : 'آخر التسليمات' }}
              </button>
              <button
                v-if="ep.is_active"
                type="button"
                class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300"
                @click="deactivateWebhook(ep.id)"
              >
                تعطيل
              </button>
            </div>
            <div v-if="deliveriesForId === ep.id && deliveryRows.length" class="rounded-lg bg-gray-50 dark:bg-slate-900/50 text-xs overflow-x-auto max-h-40 overflow-y-auto">
              <table class="w-full">
                <thead><tr class="text-gray-500"><th class="px-2 py-1 text-right">#</th><th class="px-2 py-1 text-right">HTTP</th><th class="px-2 py-1 text-right">وقت</th></tr></thead>
                <tbody>
                  <tr v-for="d in deliveryRows" :key="d.id" class="border-t border-gray-200 dark:border-slate-700">
                    <td class="px-2 py-1 font-mono">{{ d.id }}</td>
                    <td class="px-2 py-1">{{ d.status_code ?? '—' }}</td>
                    <td class="px-2 py-1 font-mono">{{ d.created_at ?? '' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else-if="deliveriesForId === ep.id" class="text-xs text-gray-400">لا توجد تسليمات مسجّلة.</p>
          </div>
          <p v-if="!webhookEndpoints.length && !webhookLoading" class="text-sm text-gray-400 text-center py-4">لا توجد Webhooks بعد.</p>
        </div>

        <div class="border-t border-gray-100 dark:border-slate-700 pt-5 space-y-3">
          <h4 class="text-sm font-semibold text-gray-800 dark:text-slate-200">إضافة Webhook</h4>
          <div>
            <label class="block text-xs text-gray-500 mb-1">رابط HTTPS</label>
            <input v-model="newWebhook.url" type="url" class="field font-mono text-sm" placeholder="https://api.example.com/hooks/asaspro" />
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-2">الأحداث</p>
            <div class="flex flex-wrap gap-2">
              <label v-for="ev in webhookEventPresets" :key="ev" class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-lg border border-gray-200 dark:border-slate-600 cursor-pointer">
                <input v-model="newWebhook.events" type="checkbox" :value="ev" class="rounded" />
                {{ ev }}
              </label>
            </div>
          </div>
          <button
            type="button"
            class="px-5 py-2 bg-slate-800 dark:bg-slate-200 dark:text-slate-900 text-white rounded-lg text-sm disabled:opacity-50"
            :disabled="webhookSaving || !newWebhook.url.trim() || !newWebhook.events.length"
            @click="submitNewWebhook"
          >
            {{ webhookSaving ? 'جارٍ الإنشاء...' : 'إنشاء وحفظ السر' }}
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="webhookSecretModal" class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-black/55" @click.self="webhookSecretModal = ''">
        <div class="bg-white dark:bg-slate-900 rounded-xl border max-w-lg w-full p-5 space-y-3" @click.stop>
          <h4 class="font-bold text-gray-900 dark:text-slate-100">احفظ التوقيع الآن</h4>
          <p class="text-xs text-red-600 dark:text-red-400">لن يُعرض هذا المفتاح مرة أخرى. انسخه إلى مدير أسرار آمن.</p>
          <textarea :value="webhookSecretModal" readonly rows="3" class="w-full font-mono text-xs p-3 border rounded-lg bg-gray-50 dark:bg-slate-800 dark:border-slate-600" />
          <button type="button" class="w-full py-2 text-sm bg-primary-600 text-white rounded-lg" @click="copySecret">نسخ إلى الحافظة</button>
          <button type="button" class="w-full py-2 text-sm border rounded-lg" @click="webhookSecretModal = ''">أغلقتُ — فهمت</button>
        </div>
      </div>
    </Teleport>

    <!-- ══════ Booking Portal Tab ══════ -->
    <section v-show="activeTab === 'portal'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center"><GlobeAltIcon class="w-5 h-5 text-indigo-600" /></div>
        <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">بوابة الحجوزات</h3><p class="text-xs text-gray-400">رابط عام للحجوزات بدومين خاص أو من المنصة</p></div>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button class="flex items-start gap-3 p-4 rounded-xl border-2 text-right transition-all" :class="bookingPortal.domainType === 'subdomain' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-slate-600'" @click="bookingPortal.domainType = 'subdomain'">
            <LinkIcon class="w-5 h-5 text-indigo-500 mt-0.5" />
            <div><p class="text-sm font-semibold text-gray-900 dark:text-slate-100">رابط من المنصة</p><p class="text-xs text-gray-400 mt-0.5">book.workshopos.sa/اسمك</p><span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full mt-1 inline-block">جميع الباقات</span></div>
          </button>
          <button :disabled="!canCustomDomain" class="flex items-start gap-3 p-4 rounded-xl border-2 text-right transition-all" :class="[bookingPortal.domainType === 'custom' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-slate-600', !canCustomDomain ? 'opacity-60 cursor-not-allowed' : '']" @click="bookingPortal.domainType = 'custom'">
            <GlobeAltIcon class="w-5 h-5 mt-0.5" :class="bookingPortal.domainType === 'custom' ? 'text-primary-500' : 'text-gray-400'" />
            <div><p class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-1">دومين خاص <LockClosedIcon v-if="!canCustomDomain" class="w-3 h-3 text-gray-400" /></p><p class="text-xs text-gray-400 mt-0.5">booking.yourworkshop.sa</p><span class="text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block" :class="canCustomDomain ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-400'">{{ canCustomDomain ? 'متاح' : 'Enterprise' }}</span></div>
          </button>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ bookingPortal.domainType === 'subdomain' ? 'اسم مركز الخدمة أو المنفذ في الرابط' : 'دومينك الخاص' }}</label>
          <div v-if="bookingPortal.domainType === 'subdomain'" class="flex rounded-lg border border-gray-300 dark:border-slate-600 overflow-hidden">
            <span class="px-3 py-2 bg-gray-50 dark:bg-slate-700 text-xs text-gray-500 border-l dark:border-slate-500 whitespace-nowrap">book.workshopos.sa/</span>
            <input v-model="bookingPortal.slug" class="flex-1 px-3 py-2 text-sm outline-none dark:bg-slate-800 font-mono" placeholder="elite-auto" @input="bookingPortal.slug = bookingPortal.slug.replace(/[^a-z0-9-]/g, '').toLowerCase()" />
          </div>
          <input v-else v-model="bookingPortal.customDomain" class="field font-mono" placeholder="booking.yourworkshop.sa" />
        </div>
        <button :disabled="savingPortal" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50" @click="saveBookingPortal">{{ savingPortal ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
      </div>
    </section>

    <!-- ══════ POS Hardware Profiles ══════ -->
    <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center justify-between">
        <div>
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">ربط أجهزة الكاشير (POS Hardware)</h3>
          <p class="text-xs text-gray-400">تعريف أكثر من جهاز وربطه بالفروع مع اختبار اتصال سريع.</p>
        </div>
        <button class="px-3 py-1.5 text-xs bg-primary-600 text-white rounded-lg hover:bg-primary-700" @click="addPosProfile">إضافة جهاز</button>
      </div>
      <div class="p-5 space-y-3">
        <div
          v-for="(p, i) in posProfiles"
          :key="i"
          class="rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2"
        >
          <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <input v-model="p.name" class="field" placeholder="اسم الجهاز" />
            <input v-model="p.vendor" class="field" placeholder="المزود / النظام" />
            <input v-model="p.ip" class="field font-mono" placeholder="IP أو Host" />
            <select v-model="p.branch_id" class="field">
              <option value="">بدون ربط فرع</option>
              <option v-for="b in bays" :key="`pos-b-${b.id}`" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <button class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 hover:bg-gray-50" @click="testPosProfile(i)">اختبار اتصال</button>
            <span class="text-xs" :class="p.ok ? 'text-green-600' : 'text-gray-400'">{{ p.ok ? 'متصل' : 'غير مختبر' }}</span>
            <button class="mr-auto text-xs text-red-500 hover:underline" @click="posProfiles.splice(i,1)">حذف</button>
          </div>
        </div>
        <button :disabled="savingPosProfiles" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50" @click="savePosProfiles">
          {{ savingPosProfiles ? 'جارٍ الحفظ...' : 'حفظ أجهزة الكاشير' }}
        </button>
      </div>
    </section>

    <!-- ══════ Documents Notification Channels ══════ -->
    <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">قنوات تنبيهات المستندات</h3>
        <p class="text-xs text-gray-400">تفعيل قناة التنبيه وجدولة أيام التذكير قبل انتهاء المستند.</p>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
            <span class="text-sm">تنبيه داخل النظام</span>
            <input v-model="docsNotify.in_app" type="checkbox" class="rounded" />
          </label>
          <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
            <span class="text-sm">تنبيه بريد</span>
            <input v-model="docsNotify.email" type="checkbox" class="rounded" />
          </label>
          <label class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
            <span class="text-sm">تنبيه واتساب</span>
            <input v-model="docsNotify.whatsapp" type="checkbox" class="rounded" />
          </label>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">أيام التذكير (CSV) مثال: 30,7,1</label>
          <input v-model="docsNotify.reminder_days_csv" class="field font-mono" />
        </div>
        <button :disabled="savingDocsNotify" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50" @click="saveDocsNotify">
          {{ savingDocsNotify ? 'جارٍ الحفظ...' : 'حفظ إعدادات التنبيه' }}
        </button>
      </div>
    </section>

    <!-- ══════ Supplier contracts expiry notifications ══════ -->
    <section
      v-if="biz.isEnabled('supplier_contract_mgmt')"
      class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden"
    >
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">تنبيهات انتهاء عقود الموردين</h3>
        <p class="text-xs text-gray-400">
          تذكيرات قبل تاريخ انتهاء العقود (PDF). يعمل مع
          <RouterLink to="/suppliers" class="text-primary-600 hover:underline">موردي المخزون</RouterLink>
          — مفعّل فقط عند تفعيل «عقود الموردين» في نشاط المنشأة.
        </p>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <label class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-slate-600 p-3">
            <span class="text-sm">تنبيه داخل النظام</span>
            <input v-model="supplierContractNotify.in_app" type="checkbox" class="rounded" />
          </label>
          <label class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-slate-600 p-3">
            <span class="text-sm">تنبيه بريد</span>
            <input v-model="supplierContractNotify.email" type="checkbox" class="rounded" />
          </label>
          <label class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-slate-600 p-3">
            <span class="text-sm">تنبيه واتساب</span>
            <input v-model="supplierContractNotify.whatsapp" type="checkbox" class="rounded" />
          </label>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">أيام التذكير (CSV) مثال: 30,7,1 · أضف 0 لتنبيه بعد انتهاء العقد</label>
          <input v-model="supplierContractNotify.reminder_days_csv" class="field font-mono" />
        </div>
        <button
          :disabled="savingSupplierContractNotify"
          class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50"
          @click="saveSupplierContractNotify"
        >
          {{ savingSupplierContractNotify ? 'جارٍ الحفظ...' : 'حفظ تنبيهات العقود' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, computed, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ChatBubbleLeftEllipsisIcon, GlobeAltIcon, LinkIcon, LockClosedIcon, EnvelopeIcon,
  VideoCameraIcon, MapPinIcon, StarIcon, PlusIcon, BoltIcon,
} from '@heroicons/vue/24/outline'
import { useSubscriptionStore } from '@/stores/subscription'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import apiClient from '@/lib/apiClient'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'

const sub   = useSubscriptionStore()
const auth = useAuthStore()
const biz = useBusinessProfileStore()
const toast = useToast()
const canCustomDomain = sub.hasFeature('api_access')

const activeTab = ref('whatsapp')

const tabs = computed(() => {
  const rows: { id: string; label: string; icon: typeof ChatBubbleLeftEllipsisIcon }[] = [
    { id: 'whatsapp', label: 'واتساب', icon: ChatBubbleLeftEllipsisIcon },
    { id: 'email', label: 'البريد', icon: EnvelopeIcon },
    { id: 'cameras', label: 'الكاميرات', icon: VideoCameraIcon },
    { id: 'tracking', label: 'التتبع', icon: MapPinIcon },
    { id: 'loyalty', label: 'الولاء', icon: StarIcon },
  ]
  if (auth.hasPermission('webhooks.manage')) {
    rows.push({ id: 'webhooks', label: 'Webhooks', icon: BoltIcon })
  }
  rows.push({ id: 'portal', label: 'الحجوزات', icon: GlobeAltIcon })
  return rows
})

watch(
  tabs,
  (t) => {
    const ids = t.map((x) => x.id)
    if (!ids.includes(activeTab.value)) {
      activeTab.value = ids[0] ?? 'whatsapp'
    }
  },
  { immediate: true },
)

function onSelectTab(id: string) {
  activeTab.value = id
  if (id === 'webhooks' && auth.hasPermission('webhooks.manage')) {
    void loadWebhooks()
  }
}

const webhookEndpoints = ref<Array<{ id: number; url: string; events: unknown; is_active: boolean }>>([])
const webhookLoading = ref(false)
const webhookLoadError = ref('')
const webhookSaving = ref(false)
const webhookSecretModal = ref('')
const deliveriesForId = ref<number | null>(null)
const deliveryRows = ref<Array<{ id: number; status_code?: number; created_at?: string }>>([])
const webhookEventPresets = ['invoice.created', 'invoice.paid', 'payment.received', 'customer.created', 'work_order.completed']
const newWebhook = reactive<{ url: string; events: string[] }>({ url: '', events: [] })

function formatWebhookEvents(events: unknown): string {
  if (Array.isArray(events)) return events.join(', ')
  if (events && typeof events === 'object') return JSON.stringify(events)
  return String(events ?? '')
}

async function loadWebhooks() {
  if (!auth.hasPermission('webhooks.manage')) return
  webhookLoadError.value = ''
  webhookLoading.value = true
  deliveriesForId.value = null
  try {
    const { data } = await apiClient.get('/webhooks')
    webhookEndpoints.value = Array.isArray(data.data) ? data.data : []
  } catch {
    webhookLoadError.value = 'تعذّر تحميل قائمة Webhooks.'
    webhookEndpoints.value = []
  } finally {
    webhookLoading.value = false
  }
}

async function toggleDeliveries(endpointId: number) {
  if (deliveriesForId.value === endpointId) {
    deliveriesForId.value = null
    deliveryRows.value = []
    return
  }
  deliveriesForId.value = endpointId
  try {
    const { data } = await apiClient.get(`/webhooks/${endpointId}/deliveries`, { params: { per_page: 20 } })
    const raw = data?.data
    if (Array.isArray(raw)) {
      deliveryRows.value = raw
    } else if (raw && typeof raw === 'object' && Array.isArray((raw as { data?: unknown[] }).data)) {
      deliveryRows.value = (raw as { data: typeof deliveryRows.value }).data
    } else {
      deliveryRows.value = []
    }
  } catch {
    deliveryRows.value = []
  }
}

async function deactivateWebhook(id: number) {
  const ok = await appConfirm({
    title: 'تعطيل Webhook',
    message: 'تعطيل هذا الـ Webhook؟',
    confirmLabel: 'تعطيل',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/webhooks/${id}`)
    toast.success('تم التعطيل')
    await loadWebhooks()
  } catch {
    toast.error('تعذّر التعطيل')
  }
}

async function submitNewWebhook() {
  webhookSaving.value = true
  try {
    const { data } = await apiClient.post('/webhooks', {
      url: newWebhook.url.trim(),
      events: [...newWebhook.events],
    })
    const secret = typeof data.secret === 'string' ? data.secret : ''
    if (secret) {
      webhookSecretModal.value = secret
    }
    newWebhook.url = ''
    newWebhook.events = []
    toast.success('تم إنشاء Webhook')
    await loadWebhooks()
  } catch {
    toast.error('تعذّر الإنشاء — تحقق من الرابط والأحداث والصلاحيات')
  } finally {
    webhookSaving.value = false
  }
}

async function copySecret() {
  if (!webhookSecretModal.value) return
  try {
    await navigator.clipboard.writeText(webhookSecretModal.value)
    toast.success('تم النسخ')
  } catch {
    toast.error('تعذّر النسخ')
  }
}

// ── WhatsApp ──
const waProviders = [
  { id: 'platform', emoji: '🟢', name: 'رقم المنصة', desc: 'إرسال عبر رقمنا الموحّد', requiresPro: false, badge: 'الأسهل', badgeClass: 'bg-green-100 text-green-700' },
  { id: 'twilio',   emoji: '📱', name: 'Twilio',     desc: 'مزود عالمي',              requiresPro: true,  badge: 'شائع',    badgeClass: 'bg-blue-100 text-blue-700' },
  { id: 'custom_api', emoji: '⚙️', name: 'مزود مخصص', desc: 'أي مزود REST',          requiresPro: true,  badge: 'مرن',     badgeClass: 'bg-primary-100 text-primary-700' },
]
const waSettings = reactive({ provider: 'platform', twilio_sid: '', twilio_token: '', twilio_from: '', custom_api_url: '', custom_api_key: '', custom_from: '' })
const waTriggers = reactive([
  { key: 'invoice_created',   label: 'إرسال الفاتورة',       desc: 'عند إصدار الفاتورة',      enabled: true  },
  { key: 'booking_confirmed', label: 'تأكيد الحجز',          desc: 'عند اعتماد الحجز',         enabled: true  },
  { key: 'booking_reminder',  label: 'تذكير بالموعد',        desc: 'قبل 24 ساعة',              enabled: false },
  { key: 'wo_completed',      label: 'اكتمال أمر العمل',     desc: 'جاهزية السيارة',            enabled: true  },
  { key: 'wo_delivered',      label: 'تسليم أمر العمل',      desc: 'بعد تسليم المركبة للعميل',   enabled: false },
  { key: 'payment_received',  label: 'استلام الدفع',         desc: 'إيصال دفع',                 enabled: false },
  { key: 'low_balance',       label: 'تحذير انخفاض الرصيد',  desc: 'عند 20% من رصيد المحفظة',  enabled: true  },
])
const savingWA = ref(false)

// ── Email ──
const emailProviders = [
  { id: 'platform', emoji: '📨', name: 'بريد المنصة', desc: 'بدون إعداد' },
  { id: 'smtp',     emoji: '🔧', name: 'SMTP مخصص',  desc: 'أي سيرفر بريد' },
  { id: 'sendgrid', emoji: '📧', name: 'SendGrid',    desc: 'مزود متخصص' },
]
const emailSettings = reactive({ provider: 'platform', host: '', port: '587', user: '', pass: '', from_name: '', from_email: '', sendgrid_key: '' })
const savingEmail = ref(false)

// ── Cameras ──
interface Camera { name: string; type: string; protocol: string; url: string; username: string; password: string; bay_id: string | number; active: boolean; previewActive: boolean }
const cameras = reactive<Camera[]>([])
const bays = ref<any[]>([])
const savingCameras = ref(false)

function addCamera() {
  cameras.push({ name: `كاميرا ${cameras.length + 1}`, type: 'general', protocol: 'rtsp', url: '', username: '', password: '', bay_id: '', active: true, previewActive: false })
}
function testCameraStream(i: number) {
  cameras[i].previewActive = !cameras[i].previewActive
}

// ── Tracking ──
const trackingProviders = [
  { id: 'none',       emoji: '🚫', name: 'بدون ربط',    desc: '' },
  { id: 'gps_sa',     emoji: '📡', name: 'نظام GPS SA',  desc: 'محلي' },
  { id: 'ituran',     emoji: '🛰️', name: 'Ituran',       desc: 'إيتوران' },
  { id: 'custom_api', emoji: '⚙️', name: 'API مخصص',    desc: 'أي نظام' },
]
const dashcamProviders = [
  { id: 'local',    emoji: '💾', name: 'تخزين محلي',  desc: 'NVR / NAS داخلي' },
  { id: 'cloud',    emoji: '☁️', name: 'سحابي',       desc: 'AWS / Google' },
  { id: 'custom',   emoji: '⚙️', name: 'مزود خاص',    desc: 'API خاص' },
]
const trackingSettings = reactive({ provider: 'none', api_key: '', dashboard_url: '' })
const dashcam = reactive({ enabled: false, provider: 'local', api_key: '', retention_days: '30', auto_capture_on_arrival: false })
const savingTracking = ref(false)

// ── Loyalty ──
const loyaltyTiers = reactive([
  { name: 'برونزي', emoji: '🥉', color: '#cd7f32', from: 0,    multiplier: 1 },
  { name: 'فضي',    emoji: '🥈', color: '#9ca3af', from: 500,  multiplier: 1.5 },
  { name: 'ذهبي',   emoji: '🥇', color: '#f59e0b', from: 2000, multiplier: 2 },
  { name: 'بلاتيني', emoji: '💎', color: '#8b5cf6', from: 5000, multiplier: 3 },
])
const externalLoyalty = [
  { id: 'itmam',   emoji: '🇸🇦', name: 'إتمام',       desc: 'السعودية' },
  { id: 'punchh',  emoji: '🌐', name: 'Punchh',      desc: 'عالمي' },
  { id: 'yoyo',    emoji: '🔄', name: 'YoYo Wallet', desc: 'محلي' },
  { id: 'custom',  emoji: '⚙️', name: 'API مخصص',   desc: 'أي نظام' },
]
const loyalty = reactive({ enabled: false, points_per_riyal: 1, point_value_in_halala: 10, min_redeem_points: 500, external: '', external_api_key: '', external_program_id: '' })
const savingLoyalty = ref(false)

// ── Booking Portal ──
const bookingPortal = reactive({ domainType: 'subdomain' as 'subdomain' | 'custom', slug: '', customDomain: '' })
const savingPortal = ref(false)
const posProfiles = reactive<Array<{ name: string; vendor: string; ip: string; branch_id: string | number; ok?: boolean }>>([])
const savingPosProfiles = ref(false)
const docsNotify = reactive({ in_app: true, email: false, whatsapp: false, reminder_days_csv: '30,7,1' })
const savingDocsNotify = ref(false)
const supplierContractNotify = reactive({ in_app: true, email: false, whatsapp: false, reminder_days_csv: '30,7,1' })
const savingSupplierContractNotify = ref(false)

// ── API Calls (مسارات حقيقية: PATCH /companies/{id}/settings) ──
function companySettingsUrl(): string {
  const id = auth.user?.company_id
  if (!id) throw new Error('no company')
  return `/companies/${id}/settings`
}

async function saveWA() {
  if (!auth.user?.company_id) return
  savingWA.value = true
  try {
    await apiClient.patch(companySettingsUrl(), {
      whatsapp: {
        provider: waSettings.provider,
        config: { ...waSettings },
        triggers: waTriggers.reduce<Record<string, boolean>>((a, t) => ({ ...a, [t.key]: t.enabled }), {}),
      },
    })
    toast.success('تم حفظ إعدادات واتساب')
  } catch {
    toast.error('تعذّر حفظ إعدادات واتساب')
  } finally {
    savingWA.value = false
  }
}
async function testWA() {
  const cid = auth.user?.company_id
  if (!cid) return
  try {
    const { data } = await apiClient.post(`/companies/${cid}/settings/test-channel`, { channel: 'whatsapp' })
    toast.success(String(data?.message ?? 'تم الطلب'))
  } catch {
    toast.error('تعذّر طلب الاختبار')
  }
}
async function saveEmail() {
  if (!auth.user?.company_id) return
  savingEmail.value = true
  try {
    await apiClient.patch(companySettingsUrl(), { email: { ...emailSettings } })
    toast.success('تم حفظ إعدادات البريد')
  } catch {
    toast.error('تعذّر حفظ إعدادات البريد')
  } finally {
    savingEmail.value = false
  }
}
async function testEmail() {
  const cid = auth.user?.company_id
  if (!cid) return
  try {
    const { data } = await apiClient.post(`/companies/${cid}/settings/test-channel`, { channel: 'email' })
    toast.success(String(data?.message ?? 'تم الطلب'))
  } catch {
    toast.error('تعذّر طلب الاختبار')
  }
}
async function saveCameras() {
  if (!auth.user?.company_id) return
  savingCameras.value = true
  try {
    await apiClient.patch(companySettingsUrl(), { cameras: [...cameras] })
    toast.success('تم حفظ إعدادات الكاميرات')
  } catch {
    toast.error('تعذّر حفظ الكاميرات')
  } finally {
    savingCameras.value = false
  }
}
async function saveTracking() {
  if (!auth.user?.company_id) return
  savingTracking.value = true
  try {
    await apiClient.patch(companySettingsUrl(), {
      tracking: { ...trackingSettings },
      dashcam: { ...dashcam },
    })
    toast.success('تم حفظ إعدادات التتبع')
  } catch {
    toast.error('تعذّر حفظ التتبع')
  } finally {
    savingTracking.value = false
  }
}
async function saveLoyalty() {
  if (!auth.user?.company_id) return
  savingLoyalty.value = true
  try {
    await apiClient.patch(companySettingsUrl(), { loyalty: { ...loyalty, tiers: [...loyaltyTiers] } })
    toast.success('تم حفظ إعدادات الولاء')
  } catch {
    toast.error('تعذّر حفظ الولاء')
  } finally {
    savingLoyalty.value = false
  }
}
async function saveBookingPortal() {
  if (!auth.user?.company_id) return
  savingPortal.value = true
  try {
    await apiClient.patch(companySettingsUrl(), { booking_portal: { ...bookingPortal } })
    toast.success('تم حفظ بوابة الحجوزات')
  } catch {
    toast.error('تعذّر حفظ بوابة الحجوزات')
  } finally {
    savingPortal.value = false
  }
}

function addPosProfile() {
  posProfiles.push({ name: '', vendor: '', ip: '', branch_id: '', ok: false })
}

async function testPosProfile(i: number) {
  const profile = posProfiles[i]
  if (!profile?.ip) return
  if (!auth.user?.company_id) return
  try {
    const protocol = profile.ip.startsWith('https://') ? 'https' : profile.ip.startsWith('http://') ? 'http' : 'tcp'
    const { data } = await apiClient.post(`/companies/${auth.user.company_id}/pos/test-connection`, {
      ip: profile.ip,
      protocol,
      timeout_ms: 1800,
    })
    profile.ok = Boolean(data?.data?.ok)
  } catch {
    profile.ok = false
  }
}

async function savePosProfiles() {
  if (!auth.user?.company_id) return
  savingPosProfiles.value = true
  try {
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      pos: {
        profiles: posProfiles,
        connected_cashier: posProfiles.some((p) => p.ok),
        device_name: posProfiles[0]?.name ?? '',
        external_pos_enabled: posProfiles.length > 0,
      },
    })
    toast.success('تم حفظ ربط أجهزة الكاشير')
  } finally {
    savingPosProfiles.value = false
  }
}

async function saveDocsNotify() {
  if (!auth.user?.company_id) return
  savingDocsNotify.value = true
  try {
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      documents_notifications: {
        in_app: docsNotify.in_app,
        email: docsNotify.email,
        whatsapp: docsNotify.whatsapp,
        reminder_days: docsNotify.reminder_days_csv.split(',').map((x) => Number(x.trim())).filter((n) => Number.isFinite(n) && n >= 0),
      },
    })
    toast.success('تم حفظ قنوات تنبيهات المستندات')
  } finally {
    savingDocsNotify.value = false
  }
}

async function saveSupplierContractNotify() {
  if (!auth.user?.company_id) return
  savingSupplierContractNotify.value = true
  try {
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      supplier_contract_notifications: {
        in_app: supplierContractNotify.in_app,
        email: supplierContractNotify.email,
        whatsapp: supplierContractNotify.whatsapp,
        reminder_days: supplierContractNotify.reminder_days_csv
          .split(',')
          .map((x) => Number(x.trim()))
          .filter((n) => Number.isFinite(n) && n >= 0),
      },
    })
    toast.success('تم حفظ تنبيهات عقود الموردين')
  } catch {
    toast.error('تعذّر الحفظ')
  } finally {
    savingSupplierContractNotify.value = false
  }
}

function applyLoadedCompanySettings(s: Record<string, unknown>): void {
  const w = s.whatsapp
  if (w && typeof w === 'object') {
    const o = w as { provider?: string; config?: Record<string, unknown>; triggers?: Record<string, boolean> }
    if (o.provider) waSettings.provider = o.provider as typeof waSettings.provider
    if (o.config && typeof o.config === 'object') {
      Object.assign(waSettings, o.config)
    }
    if (o.triggers && typeof o.triggers === 'object') {
      for (const t of waTriggers) {
        if (typeof o.triggers[t.key] === 'boolean') t.enabled = o.triggers[t.key] as boolean
      }
    }
  }
  const em = s.email
  if (em && typeof em === 'object') Object.assign(emailSettings, em as object)
  if (Array.isArray(s.cameras)) {
    cameras.splice(0, cameras.length, ...(s.cameras as Camera[]))
  }
  const tr = s.tracking
  if (tr && typeof tr === 'object') Object.assign(trackingSettings, tr as object)
  const dc = s.dashcam
  if (dc && typeof dc === 'object') Object.assign(dashcam, dc as object)
  const ly = s.loyalty
  if (ly && typeof ly === 'object') {
    const L = ly as { tiers?: typeof loyaltyTiers; [k: string]: unknown }
    Object.assign(loyalty, ly)
    if (Array.isArray(L.tiers) && L.tiers.length) {
      loyaltyTiers.splice(0, loyaltyTiers.length, ...L.tiers)
    }
  }
  const bp = s.booking_portal
  if (bp && typeof bp === 'object') Object.assign(bookingPortal, bp as object)
}

onMounted(async () => {
  try {
    await biz.load()
  } catch {
    /* ignore */
  }
  try {
    const r = await apiClient.get('/bays?per_page=50')
    bays.value = r.data.data || []
  } catch {
    /* ignore */
  }
  if (!auth.user?.company_id) return
  try {
    const { data } = await apiClient.get(`/companies/${auth.user.company_id}/settings`)
    const s = (data?.data ?? {}) as Record<string, unknown>
    applyLoadedCompanySettings(s)
    const rawProfiles = Array.isArray((s.pos as { profiles?: unknown } | undefined)?.profiles)
      ? (s.pos as { profiles: unknown[] }).profiles
      : []
    const normalized = rawProfiles.filter(
      (p): p is { name: string; vendor: string; ip: string; branch_id: string | number; ok?: boolean } =>
        p !== null && typeof p === 'object' && 'ip' in p,
    )
    posProfiles.splice(0, posProfiles.length, ...normalized)
    if (s?.documents_notifications) {
      const dn = s.documents_notifications as { in_app?: boolean; email?: boolean; whatsapp?: boolean; reminder_days?: number[] }
      docsNotify.in_app = Boolean(dn.in_app)
      docsNotify.email = Boolean(dn.email)
      docsNotify.whatsapp = Boolean(dn.whatsapp)
      const days = Array.isArray(dn.reminder_days) ? dn.reminder_days : [30, 7, 1]
      docsNotify.reminder_days_csv = days.join(',')
    }
    if (s?.supplier_contract_notifications) {
      const sc = s.supplier_contract_notifications as { in_app?: boolean; email?: boolean; whatsapp?: boolean; reminder_days?: number[] }
      supplierContractNotify.in_app = Boolean(sc.in_app)
      supplierContractNotify.email = Boolean(sc.email)
      supplierContractNotify.whatsapp = Boolean(sc.whatsapp)
      const scDays = Array.isArray(sc.reminder_days) ? sc.reminder_days : [30, 7, 1]
      supplierContractNotify.reminder_days_csv = scDays.join(',')
    }
  } catch {
    // ignore
  }
})
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-slate-700 dark:text-slate-100; }
</style>
