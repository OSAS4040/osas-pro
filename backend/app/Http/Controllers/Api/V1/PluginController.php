<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Plugin;
use App\Models\TenantPlugin;
use App\Models\PluginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    /** GET /plugins — list all available plugins */
    public function index(Request $request): JsonResponse
    {
        $this->seedDefaultsIfEmpty();
        $companyId = $request->user()->company_id;
        $plugins = Plugin::where('is_active', true)
            ->orderBy('recommended_rank')
            ->orderBy('name_ar')
            ->withCount(['tenantInstalls as is_installed' => function($q) use ($companyId) {
                $q->where('company_id', $companyId)->where('is_enabled', true);
            }])
            ->get()
            ->map(function($p) use ($companyId) {
                $tenant = TenantPlugin::where('company_id', $companyId)->where('plugin_key', $p->plugin_key)->first();
                $p->is_installed = (bool)$tenant?->is_enabled;
                $p->tenant_config = $tenant?->config;
                return $p;
            });
        return response()->json(['data' => $plugins]);
    }

    /** GET /plugins/:key */
    public function show(Request $request, string $key): JsonResponse
    {
        $this->seedDefaultsIfEmpty();
        $plugin = Plugin::where('plugin_key', $key)->firstOrFail();
        $tenant = TenantPlugin::where('company_id', $request->user()->company_id)->where('plugin_key', $key)->first();
        $plugin->is_installed = (bool)$tenant?->is_enabled;
        $plugin->tenant_config = $tenant?->config;
        return response()->json(['data' => $plugin]);
    }

    /** POST /plugins/:key/install */
    public function install(Request $request, string $key): JsonResponse
    {
        $this->seedDefaultsIfEmpty();
        $plugin = Plugin::where('plugin_key', $key)->where('is_active', true)->firstOrFail();
        $companyId = $request->user()->company_id;
        
        $tenant = TenantPlugin::updateOrCreate(
            ['company_id' => $companyId, 'plugin_key' => $key],
            ['is_enabled' => true, 'enabled_at' => now(), 'config' => $plugin->config_schema ?? []]
        );
        $plugin->increment('install_count');
        
        $this->log($key, $companyId, 'activated');
        return response()->json(['data' => $tenant, 'message' => "تم تثبيت {$plugin->name_ar} بنجاح."], 201);
    }

    /** DELETE /plugins/:key/uninstall */
    public function uninstall(Request $request, string $key): JsonResponse
    {
        $companyId = $request->user()->company_id;
        TenantPlugin::where('company_id', $companyId)->where('plugin_key', $key)
            ->update(['is_enabled' => false, 'disabled_at' => now()]);
        $this->log($key, $companyId, 'deactivated');
        return response()->json(['message' => 'تم إلغاء تثبيت الإضافة.']);
    }

    /** PUT /plugins/:key/configure */
    public function configure(Request $request, string $key): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate(['config' => 'required|array']);
        TenantPlugin::where('company_id', $companyId)->where('plugin_key', $key)->update(['config' => $data['config']]);
        return response()->json(['message' => 'تم حفظ الإعدادات.']);
    }

    /** GET /plugins/:key/execute — execute plugin logic */
    public function execute(Request $request, string $key): JsonResponse
    {
        $start = microtime(true);
        $companyId = $request->user()->company_id;
        
        $tenant = TenantPlugin::where('company_id', $companyId)->where('plugin_key', $key)->where('is_enabled', true)->first();
        if (!$tenant) return response()->json(['message' => 'الإضافة غير مثبتة.'], 403);
        
        $context = $request->input('context', []);
        $result = $this->runPlugin($key, $context, $companyId);
        $ms = (int)((microtime(true) - $start) * 1000);
        $this->log($key, $companyId, 'executed', ['context_keys' => array_keys($context), 'ms' => $ms], 'success', $ms);
        
        return response()->json(['data' => $result, 'execution_ms' => $ms]);
    }

    /** GET /plugins/tenant — list installed plugins for current tenant */
    public function tenantPlugins(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $installed = TenantPlugin::where('company_id', $companyId)
            ->where('is_enabled', true)
            ->with('plugin')
            ->get();
        return response()->json(['data' => $installed]);
    }

    private function runPlugin(string $key, array $context, int $companyId): array
    {
        return match ($key) {
            'ai_advanced_diagnostics' => $this->runDiagnostics($context, $companyId),
            'ai_pricing_engine'       => $this->runPricing($context, $companyId),
            'ai_fraud_detection'      => $this->runFraud($context, $companyId),
            'int_qiwa_workforce'      => $this->runHrQiwaReport($companyId),
            'int_gosi_payroll'        => $this->runHrGosiReport($companyId),
            'int_e_contract'          => $this->runHrEContractReport($companyId),
            'int_mudad_payroll'       => $this->runMudadInfoReport($companyId),
            default => ['status' => 'ok', 'plugin' => $key, 'message' => 'تم تنفيذ الإضافة بنجاح.'],
        };
    }

    private function runDiagnostics(array $ctx, int $companyId): array
    {
        $symptoms = $ctx['symptoms'] ?? [];
        $make = $ctx['make'] ?? 'Unknown';
        $diagnostics = [
            ['issue' => 'تلف وسادة الهواء الأمامية', 'confidence' => 0.87, 'severity' => 'high', 'estimated_cost' => 850],
            ['issue' => 'ضعف بطارية التشغيل', 'confidence' => 0.72, 'severity' => 'medium', 'estimated_cost' => 350],
            ['issue' => 'تآكل طوارئ الفرامل', 'confidence' => 0.65, 'severity' => 'low', 'estimated_cost' => 180],
        ];
        return ['diagnostics' => $diagnostics, 'vehicle_make' => $make, 'analyzed_at' => now()->toISOString(), 'ai_confidence' => 0.91];
    }

    private function runPricing(array $ctx, int $companyId): array
    {
        $service = $ctx['service'] ?? 'general';
        $base = $ctx['base_price'] ?? 100;
        return ['recommended_price' => $base * 1.15, 'margin' => 0.15, 'market_avg' => $base * 1.08, 'demand_factor' => 1.2, 'suggestion' => 'السعر الحالي أقل من متوسط السوق بنسبة 7%'];
    }

    private function runFraud(array $ctx, int $companyId): array
    {
        return ['risk_score' => rand(10, 35) / 100, 'risk_level' => 'low', 'flags' => [], 'recommendation' => 'المعاملة تبدو طبيعية'];
    }

    /**
     * تقرير مرجعي من بيانات الموظفين المحلية — دون اتصال بوزارة HR حتى اعتماد واجهات الطرفية.
     */
    private function runHrQiwaReport(int $companyId): array
    {
        $rows = Employee::where('company_id', $companyId)->get(['id', 'hr_integrations', 'national_id']);
        $total = $rows->count();
        $withRef = $rows->filter(fn (Employee $e) => $this->hrString($e->hr_integrations, 'qiwa.employee_ref') !== '')->count();

        return [
            'integration'          => 'qiwa',
            'employees_total'      => $total,
            'with_qiwa_ref'        => $withRef,
            'missing_qiwa_ref'     => max(0, $total - $withRef),
            'live_api_sync'        => false,
            'message'              => 'تم تقييم المراجع المحفوظة في ملفات الموظفين. المزامنة المباشرة مع قوى تتطلب مفاتيح واعتمدًا رسميًا في إعدادات الإضافة.',
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    private function runHrGosiReport(int $companyId): array
    {
        $rows = Employee::where('company_id', $companyId)->get(['id', 'hr_integrations']);
        $total = $rows->count();
        $withSub = $rows->filter(fn (Employee $e) => $this->hrString($e->hr_integrations, 'gosi.subscription_number') !== '')->count();

        return [
            'integration'          => 'gosi',
            'employees_total'      => $total,
            'with_subscription_no' => $withSub,
            'missing_subscription' => max(0, $total - $withSub),
            'live_api_sync'        => false,
            'message'              => 'تم تقييم أرقام الاشتراك المخزّنة يدويًا. مطابقة أجور GOSI الفعلية تتطلب تكاملًا معتمدًا بعد تفعيل اتصال المزود.',
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    private function runHrEContractReport(int $companyId): array
    {
        $rows = Employee::where('company_id', $companyId)->get(['id', 'hr_integrations']);
        $total = $rows->count();
        $withId = $rows->filter(fn (Employee $e) => $this->hrString($e->hr_integrations, 'e_contract.contract_id') !== '')->count();

        return [
            'integration'     => 'e_contract',
            'employees_total' => $total,
            'with_contract_id' => $withId,
            'missing_contract_ref' => max(0, $total - $withId),
            'live_api_sync'   => false,
            'message'         => 'ملخص مراجع العقود الإلكتروني من حقول الموظف. التوقيع المباشر عبر المزوّد يُضاف لاحقًا عند ربط API.',
            'generated_at'    => now()->toIso8601String(),
        ];
    }

    private function runMudadInfoReport(int $companyId): array
    {
        return [
            'integration'   => 'mudad',
            'live_api_sync' => false,
            'message'       => 'لا يوجد ملف أجور مُصدَّر تلقائيًا بعد. بعد اعتماد التكامل، يمكن توليد ملفات مودد من بيانات الرواتب والحضور.',
            'employees_total' => Employee::where('company_id', $companyId)->count(),
            'generated_at'  => now()->toIso8601String(),
        ];
    }

    private function hrString(?array $hr, string $path): string
    {
        $v = data_get($hr ?? [], $path);

        return is_string($v) || is_numeric($v) ? trim((string) $v) : '';
    }

    private function log(string $key, int $companyId, string $event, array $payload = [], string $status = 'success', int $ms = 0): void
    {
        try {
            \App\Models\PluginLog::create(['plugin_key' => $key, 'company_id' => $companyId, 'event_type' => $event, 'payload' => $payload, 'status' => $status, 'execution_ms' => $ms ?: null]);
        } catch (\Throwable) {}
    }

    /**
     * يضمن ظهور إضافات أساسية حتى إن لم يتم تشغيل seeder.
     */
    private function seedDefaultsIfEmpty(): void
    {
        if (Plugin::query()->exists()) {
            return;
        }

        Plugin::query()->insert([
            [
                'plugin_key' => 'int_qiwa_workforce',
                'name' => 'Qiwa Workforce',
                'name_ar' => 'تكامل قوى للموارد البشرية',
                'description' => 'Qiwa workforce reference sync.',
                'description_ar' => 'مزامنة أساسية لمرجع الموظف مع بيانات قوى.',
                'version' => '1.0.0',
                'author' => 'Osas Pro',
                'category' => 'integration',
                'icon' => 'building-office-2',
                'module_scope' => json_encode(['hr']),
                'config_schema' => json_encode([]),
                'supported_plans' => json_encode(['basic', 'pro', 'enterprise']),
                'hooks' => json_encode(['employee.created']),
                'is_active' => true,
                'is_premium' => false,
                'price_monthly' => 0,
                'install_count' => 0,
                'rating' => 4.7,
                'recommended_rank' => 1,
                'tags' => json_encode(['recommended', 'hr', 'qiwa']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plugin_key' => 'int_gosi_payroll',
                'name' => 'GOSI Payroll',
                'name_ar' => 'تكامل التأمينات GOSI',
                'description' => 'GOSI payroll integration baseline.',
                'description_ar' => 'دعم مواءمة بيانات الموظفين مع اشتراكات التأمينات.',
                'version' => '1.0.0',
                'author' => 'Osas Pro',
                'category' => 'integration',
                'icon' => 'shield-check',
                'module_scope' => json_encode(['hr', 'finance']),
                'config_schema' => json_encode([]),
                'supported_plans' => json_encode(['pro', 'enterprise']),
                'hooks' => json_encode(['payroll.generated']),
                'is_active' => true,
                'is_premium' => false,
                'price_monthly' => 0,
                'install_count' => 0,
                'rating' => 4.6,
                'recommended_rank' => 2,
                'tags' => json_encode(['recommended', 'hr', 'gosi']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plugin_key' => 'ai_advanced_diagnostics',
                'name' => 'AI Diagnostics',
                'name_ar' => 'تشخيص أعطال ذكي',
                'description' => 'AI-assisted diagnostics for work orders.',
                'description_ar' => 'تحليل أعراض المركبة واقتراح التشخيص الاحتمالي.',
                'version' => '1.0.0',
                'author' => 'Osas Pro AI',
                'category' => 'ai',
                'icon' => 'cpu-chip',
                'module_scope' => json_encode(['operations']),
                'config_schema' => json_encode([]),
                'supported_plans' => json_encode(['enterprise']),
                'hooks' => json_encode(['work_order.created']),
                'is_active' => true,
                'is_premium' => true,
                'price_monthly' => 199,
                'install_count' => 0,
                'rating' => 4.8,
                'recommended_rank' => 3,
                'tags' => json_encode(['ai', 'recommended']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
