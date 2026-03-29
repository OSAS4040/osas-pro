<template>
  <div class="space-y-6" dir="rtl">
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100">التكاملات المتقدمة</h2>
      <p class="text-sm text-gray-400 mt-0.5">ربط الورشة بالأنظمة الخارجية — الكاميرات، التتبع، الواتساب، البريد</p>
    </div>

    <!-- ══════ Tab Navigation ══════ -->
    <div class="flex gap-1 bg-gray-100 dark:bg-slate-800 p-1 rounded-xl overflow-x-auto">
      <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
        :class="activeTab === t.id ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700'">
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
          :class="sub.hasFeature('work_orders') ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'">
          {{ sub.hasFeature('work_orders') ? 'متاح' : 'يتطلب Professional' }}
        </span>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button v-for="p in waProviders" :key="p.id" @click="waSettings.provider = p.id"
            :disabled="p.requiresPro && !sub.hasFeature('work_orders')"
            class="flex items-start gap-3 p-3.5 rounded-xl border-2 text-right transition-all"
            :class="[waSettings.provider === p.id ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300', p.requiresPro && !sub.hasFeature('work_orders') ? 'opacity-50 cursor-not-allowed' : '']">
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
              <button @click="t.enabled = !t.enabled" class="relative w-10 h-5 rounded-full transition-colors flex-shrink-0" :class="t.enabled ? 'bg-green-500' : 'bg-gray-200'">
                <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="t.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
              </button>
            </label>
          </div>
        </div>
        <div class="flex gap-3">
          <button @click="saveWA" :disabled="savingWA" class="px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">{{ savingWA ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
          <button @click="testWA" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">إرسال تجريبي</button>
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
          <button v-for="p in emailProviders" :key="p.id" @click="emailSettings.provider = p.id"
            class="flex items-start gap-3 p-3.5 rounded-xl border-2 text-right transition-all"
            :class="emailSettings.provider === p.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600'">
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
          <button @click="saveEmail" :disabled="savingEmail" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">{{ savingEmail ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
          <button @click="testEmail" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700">إرسال تجريبي</button>
        </div>
      </div>
    </section>

    <!-- ══════ Cameras Tab ══════ -->
    <section v-show="activeTab === 'cameras'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center"><VideoCameraIcon class="w-5 h-5 text-purple-600" /></div>
          <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">كاميرات IP للورشة</h3><p class="text-xs text-gray-400">ربط الكاميرات لمراقبة الرافعات ومدخل الاستقبال</p></div>
        </div>
        <button @click="addCamera" class="flex items-center gap-1.5 text-xs bg-purple-600 text-white px-3 py-1.5 rounded-lg hover:bg-purple-700 transition-colors">
          <PlusIcon class="w-3.5 h-3.5" /> إضافة كاميرا
        </button>
      </div>
      <div class="p-5 space-y-4">

        <!-- Usage Explanation -->
        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800 rounded-xl p-4 text-sm text-purple-800 dark:text-purple-300">
          <p class="font-medium mb-2">كيف يعمل نظام الكاميرات؟</p>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-purple-200 dark:bg-purple-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-purple-700 dark:text-purple-300">1</span><span>كاميرا الاستقبال تقرأ لوحة المركبة تلقائياً عند الدخول</span></div>
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-purple-200 dark:bg-purple-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-purple-700 dark:text-purple-300">2</span><span>كاميرا الرافعة تكتشف وجود مركبة وتُحدّث الحالة تلقائياً</span></div>
            <div class="flex items-start gap-2"><span class="w-5 h-5 bg-purple-200 dark:bg-purple-800 rounded-full flex items-center justify-center font-bold flex-shrink-0 text-purple-700 dark:text-purple-300">3</span><span>تُلتقط صور تلقائياً عند الاستقبال والتسليم لحماية قانونية</span></div>
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
              <button @click="testCameraStream(i)" class="text-xs text-purple-600 hover:underline">اختبار</button>
              <button @click="cameras.splice(i,1)" class="text-xs text-red-500 hover:underline">حذف</button>
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
          <button @click="addCamera" class="mt-3 text-xs text-purple-600 hover:underline">+ إضافة أول كاميرا</button>
        </div>

        <button @click="saveCameras" :disabled="savingCameras" class="px-5 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50">
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
            <button v-for="p in trackingProviders" :key="p.id" @click="trackingSettings.provider = p.id"
              class="flex flex-col items-center p-4 rounded-xl border-2 transition-all gap-2"
              :class="trackingSettings.provider === p.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300'">
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
            <button @click="dashcam.enabled = !dashcam.enabled" class="relative w-10 h-5 rounded-full transition-colors" :class="dashcam.enabled ? 'bg-blue-500' : 'bg-gray-200'">
              <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="dashcam.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
            </button>
          </div>
          <div v-if="dashcam.enabled" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <button v-for="d in dashcamProviders" :key="d.id" @click="dashcam.provider = d.id"
                class="flex items-center gap-2 p-3 rounded-xl border-2 transition-all"
                :class="dashcam.provider === d.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-600'">
                <span class="text-xl">{{ d.emoji }}</span>
                <div class="text-right">
                  <p class="text-xs font-semibold text-gray-900 dark:text-slate-100">{{ d.name }}</p>
                  <p class="text-[10px] text-gray-400">{{ d.desc }}</p>
                </div>
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div><label class="block text-xs text-gray-500 mb-1">API Key للداش كام</label><input v-model="dashcam.api_key" type="password" class="field font-mono" /></div>
              <div><label class="block text-xs text-gray-500 mb-1">مدة الاحتفاظ بالفيديو</label>
                <select v-model="dashcam.retention_days" class="field">
                  <option value="7">7 أيام</option><option value="30">30 يوم</option><option value="90">90 يوم</option><option value="365">سنة</option>
                </select>
              </div>
            </div>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 dark:border-slate-700">
              <input type="checkbox" v-model="dashcam.auto_capture_on_arrival" class="rounded" />
              <div><p class="text-sm text-gray-800 dark:text-slate-200">التقاط تلقائي عند الاستقبال</p><p class="text-xs text-gray-400">يُرسَل للعميل رابط مشاهدة مقطع استقبال سيارته</p></div>
            </label>
          </div>
        </div>

        <button @click="saveTracking" :disabled="savingTracking" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
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
        <button @click="loyalty.enabled = !loyalty.enabled" class="relative w-10 h-5 rounded-full transition-colors" :class="loyalty.enabled ? 'bg-yellow-500' : 'bg-gray-200'">
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
                class="flex items-center gap-4 p-3 rounded-xl border border-gray-100 dark:border-slate-700">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg flex-shrink-0" :style="{ background: tier.color + '20', color: tier.color }">{{ tier.emoji }}</div>
                <div class="flex-1 grid grid-cols-3 gap-3">
                  <div><p class="text-xs text-gray-400">المستوى</p><p class="text-sm font-semibold" :style="{ color: tier.color }">{{ tier.name }}</p></div>
                  <div><p class="text-xs text-gray-400">من نقطة</p><input v-model.number="tier.from" type="number" class="field text-sm" /></div>
                  <div><p class="text-xs text-gray-400">مضاعف النقاط</p>
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
            <button v-for="ext in externalLoyalty" :key="ext.id" @click="loyalty.external = loyalty.external === ext.id ? '' : ext.id"
              class="flex flex-col items-center gap-2 p-3 rounded-xl border-2 transition-all text-center"
              :class="loyalty.external === ext.id ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20' : 'border-gray-200 dark:border-slate-600'">
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

        <button @click="saveLoyalty" :disabled="savingLoyalty" class="px-5 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700 disabled:opacity-50">
          {{ savingLoyalty ? 'جارٍ الحفظ...' : 'حفظ إعدادات الولاء' }}
        </button>
      </div>
    </section>

    <!-- ══════ Booking Portal Tab ══════ -->
    <section v-show="activeTab === 'portal'" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center"><GlobeAltIcon class="w-5 h-5 text-indigo-600" /></div>
        <div><h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">بوابة الحجوزات</h3><p class="text-xs text-gray-400">رابط عام للحجوزات بدومين خاص أو من المنصة</p></div>
      </div>
      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button @click="bookingPortal.domainType = 'subdomain'" class="flex items-start gap-3 p-4 rounded-xl border-2 text-right transition-all" :class="bookingPortal.domainType === 'subdomain' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-slate-600'">
            <LinkIcon class="w-5 h-5 text-indigo-500 mt-0.5" />
            <div><p class="text-sm font-semibold text-gray-900 dark:text-slate-100">رابط من المنصة</p><p class="text-xs text-gray-400 mt-0.5">book.workshopos.sa/اسمك</p><span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full mt-1 inline-block">جميع الباقات</span></div>
          </button>
          <button @click="bookingPortal.domainType = 'custom'" :disabled="!canCustomDomain" class="flex items-start gap-3 p-4 rounded-xl border-2 text-right transition-all" :class="[bookingPortal.domainType === 'custom' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-200 dark:border-slate-600', !canCustomDomain ? 'opacity-60 cursor-not-allowed' : '']">
            <GlobeAltIcon class="w-5 h-5 mt-0.5" :class="bookingPortal.domainType === 'custom' ? 'text-purple-500' : 'text-gray-400'" />
            <div><p class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-1">دومين خاص <LockClosedIcon v-if="!canCustomDomain" class="w-3 h-3 text-gray-400" /></p><p class="text-xs text-gray-400 mt-0.5">booking.yourworkshop.sa</p><span class="text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block" :class="canCustomDomain ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-400'">{{ canCustomDomain ? 'متاح' : 'Enterprise' }}</span></div>
          </button>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ bookingPortal.domainType === 'subdomain' ? 'اسم الورشة في الرابط' : 'دومينك الخاص' }}</label>
          <div v-if="bookingPortal.domainType === 'subdomain'" class="flex rounded-lg border border-gray-300 dark:border-slate-600 overflow-hidden">
            <span class="px-3 py-2 bg-gray-50 dark:bg-slate-700 text-xs text-gray-500 border-l dark:border-slate-500 whitespace-nowrap">book.workshopos.sa/</span>
            <input v-model="bookingPortal.slug" class="flex-1 px-3 py-2 text-sm outline-none dark:bg-slate-800 font-mono" placeholder="elite-auto" @input="bookingPortal.slug = bookingPortal.slug.replace(/[^a-z0-9-]/g, '').toLowerCase()" />
          </div>
          <input v-else v-model="bookingPortal.customDomain" class="field font-mono" placeholder="booking.yourworkshop.sa" />
        </div>
        <button @click="saveBookingPortal" :disabled="savingPortal" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">{{ savingPortal ? 'جارٍ الحفظ...' : 'حفظ' }}</button>
      </div>
    </section>

  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ChatBubbleLeftEllipsisIcon, GlobeAltIcon, LinkIcon, LockClosedIcon, EnvelopeIcon,
  VideoCameraIcon, MapPinIcon, StarIcon, PlusIcon,
} from '@heroicons/vue/24/outline'
import { useSubscriptionStore } from '@/stores/subscription'
import { useToast } from '@/composables/useToast'
import apiClient from '@/lib/apiClient'

const sub   = useSubscriptionStore()
const toast = useToast()
const canCustomDomain = sub.hasFeature('api_access')

const activeTab = ref('whatsapp')

const tabs = [
  { id: 'whatsapp', label: 'واتساب',     icon: ChatBubbleLeftEllipsisIcon },
  { id: 'email',    label: 'البريد',      icon: EnvelopeIcon },
  { id: 'cameras',  label: 'الكاميرات',   icon: VideoCameraIcon },
  { id: 'tracking', label: 'التتبع',      icon: MapPinIcon },
  { id: 'loyalty',  label: 'الولاء',      icon: StarIcon },
  { id: 'portal',   label: 'الحجوزات',    icon: GlobeAltIcon },
]

// ── WhatsApp ──
const waProviders = [
  { id: 'platform', emoji: '🟢', name: 'رقم المنصة', desc: 'إرسال عبر رقمنا الموحّد', requiresPro: false, badge: 'الأسهل', badgeClass: 'bg-green-100 text-green-700' },
  { id: 'twilio',   emoji: '📱', name: 'Twilio',     desc: 'مزود عالمي',              requiresPro: true,  badge: 'شائع',    badgeClass: 'bg-blue-100 text-blue-700' },
  { id: 'custom_api', emoji: '⚙️', name: 'مزود مخصص', desc: 'أي مزود REST',          requiresPro: true,  badge: 'مرن',     badgeClass: 'bg-purple-100 text-purple-700' },
]
const waSettings = reactive({ provider: 'platform', twilio_sid: '', twilio_token: '', twilio_from: '', custom_api_url: '', custom_api_key: '', custom_from: '' })
const waTriggers = reactive([
  { key: 'invoice_created',   label: 'إرسال الفاتورة',       desc: 'عند إصدار الفاتورة',      enabled: true  },
  { key: 'booking_confirmed', label: 'تأكيد الحجز',          desc: 'عند اعتماد الحجز',         enabled: true  },
  { key: 'booking_reminder',  label: 'تذكير بالموعد',        desc: 'قبل 24 ساعة',              enabled: false },
  { key: 'wo_completed',      label: 'اكتمال أمر العمل',     desc: 'جاهزية السيارة',            enabled: true  },
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

// ── API Calls ──
async function saveWA() {
  savingWA.value = true
  try {
    await apiClient.post('/settings/whatsapp', { provider: waSettings.provider, config: waSettings, triggers: waTriggers.reduce((a, t) => ({ ...a, [t.key]: t.enabled }), {}) })
    toast.success('تم حفظ إعدادات واتساب')
  } catch {} finally { savingWA.value = false }
}
async function testWA() {
  try { await apiClient.post('/settings/whatsapp/test', { provider: waSettings.provider }); toast.success('تم إرسال رسالة تجريبية') } catch {}
}
async function saveEmail() {
  savingEmail.value = true
  try { await apiClient.post('/settings/email', emailSettings); toast.success('تم حفظ إعدادات البريد') } catch {} finally { savingEmail.value = false }
}
async function testEmail() {
  try { await apiClient.post('/settings/email/test', emailSettings); toast.success('تم إرسال بريد تجريبي') } catch {}
}
async function saveCameras() {
  savingCameras.value = true
  try { await apiClient.post('/settings/cameras', { cameras }); toast.success('تم حفظ إعدادات الكاميرات') } catch {} finally { savingCameras.value = false }
}
async function saveTracking() {
  savingTracking.value = true
  try { await apiClient.post('/settings/tracking', { tracking: trackingSettings, dashcam }); toast.success('تم حفظ إعدادات التتبع') } catch {} finally { savingTracking.value = false }
}
async function saveLoyalty() {
  savingLoyalty.value = true
  try { await apiClient.post('/settings/loyalty', { ...loyalty, tiers: loyaltyTiers }); toast.success('تم حفظ إعدادات الولاء') } catch {} finally { savingLoyalty.value = false }
}
async function saveBookingPortal() {
  savingPortal.value = true
  try { await apiClient.post('/settings/booking-portal', bookingPortal); toast.success('تم حفظ بوابة الحجوزات') } catch {} finally { savingPortal.value = false }
}

onMounted(async () => {
  try { const r = await apiClient.get('/bays?per_page=50'); bays.value = r.data.data || [] } catch {}
})
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-slate-700 dark:text-slate-100; }
</style>
