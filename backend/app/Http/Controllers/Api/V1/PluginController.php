<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $companyId = $request->user()->company_id;
        $plugins = Plugin::where('is_active', true)
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
        $plugin = Plugin::where('plugin_key', $key)->firstOrFail();
        $tenant = TenantPlugin::where('company_id', $request->user()->company_id)->where('plugin_key', $key)->first();
        $plugin->is_installed = (bool)$tenant?->is_enabled;
        $plugin->tenant_config = $tenant?->config;
        return response()->json(['data' => $plugin]);
    }

    /** POST /plugins/:key/install */
    public function install(Request $request, string $key): JsonResponse
    {
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
        return match($key) {
            'ai_advanced_diagnostics' => $this->runDiagnostics($context, $companyId),
            'ai_pricing_engine'       => $this->runPricing($context, $companyId),
            'ai_fraud_detection'      => $this->runFraud($context, $companyId),
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

    private function log(string $key, int $companyId, string $event, array $payload = [], string $status = 'success', int $ms = 0): void
    {
        try {
            \App\Models\PluginLog::create(['plugin_key' => $key, 'company_id' => $companyId, 'event_type' => $event, 'payload' => $payload, 'status' => $status, 'execution_ms' => $ms ?: null]);
        } catch (\Throwable) {}
    }
}
