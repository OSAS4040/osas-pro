<?php

declare(strict_types=1);

namespace App\Companies\Profile;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Intelligence\Assemblers\CompanyIntelligenceAssembler;
use App\Models\Company;
use App\Models\User;
use App\Relationships\Operational\RelationshipMapAssembler;
use Carbon\CarbonImmutable;

/**
 * Builds {@see CompanyProfileDto} from query rows + unified intelligence (no AI).
 */
final class CompanyProfileAssembler
{
    private const PERIOD_DAYS = 30;

    private const INACTIVE_DAYS = 45;

    private const WATCH_DAYS = 14;

    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly CompanyProfileQuery $query,
        private readonly RelationshipMapAssembler $relationshipMapAssembler,
        private readonly CompanyIntelligenceAssembler $companyIntelligenceAssembler,
    ) {}

    public function assemble(Company $company, User $actor): CompanyProfileDto
    {
        $context = ($this->resolveContext)($actor, []);
        $companyId = (int) $company->id;
        $end = CarbonImmutable::now();
        $start = $end->subDays(self::PERIOD_DAYS)->startOfDay();
        $endDay = $end->endOfDay();

        $includeFinancial = $actor->hasPermission('reports.financial.view');
        $raw = $this->query->fetch($context, $companyId, $start, $endDay, $includeFinancial);

        $companyPayload = $this->companyBlock($company);
        $summary = $this->summaryBlock($raw, $includeFinancial);
        $activitySnapshot = $this->activitySnapshotBlock($raw, $includeFinancial);

        $intelBlock = $this->companyIntelligenceAssembler->assemble($company, $raw, $summary, $includeFinancial);
        $intelligence = $intelBlock['intelligence'];
        $attention = $intelBlock['attention_items_legacy'];

        $healthIndicators = $this->healthIndicatorsBridge($summary, $raw, $intelligence['health_status']);

        $relationships = $this->relationshipMapAssembler->forCompanyProfile(
            $actor,
            $companyId,
            $summary,
            [
                'top_customers' => $raw['top_customers'],
                'top_users' => $raw['top_users'],
                'branches_summary' => $raw['branches_summary'],
            ],
        );

        return new CompanyProfileDto(
            company: $companyPayload,
            summary: $summary,
            activitySnapshot: $activitySnapshot,
            healthIndicators: $healthIndicators,
            relationships: $relationships,
            attentionItems: $attention,
            intelligence: $intelligence,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function companyBlock(Company $company): array
    {
        $status = $company->status;
        $statusValue = $status instanceof \BackedEnum ? $status->value : (string) ($status ?? '');

        return [
            'id' => (int) $company->id,
            'name' => (string) $company->name,
            'status' => $statusValue,
            'type' => $company->vertical_profile_code !== null ? (string) $company->vertical_profile_code : null,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function summaryBlock(array $raw, bool $includeFinancial): array
    {
        return [
            'users_count' => (int) $raw['users_count'],
            'customers_count' => (int) $raw['customers_count'],
            'branches_count' => (int) $raw['branches_count'],
            'work_orders_active' => (int) $raw['work_orders_active'],
            'invoices_in_period' => $includeFinancial ? (int) $raw['invoices_in_period'] : null,
            'last_activity_at' => $raw['last_activity_at'],
            'activity_window_days' => self::PERIOD_DAYS,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function activitySnapshotBlock(array $raw, bool $includeFinancial): array
    {
        return [
            'last_work_order' => $raw['last_work_order'],
            'last_invoice' => $includeFinancial ? $raw['last_invoice'] : null,
            'last_payment' => $includeFinancial ? $raw['last_payment'] : null,
            'last_ticket' => $raw['last_ticket'],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function healthIndicatorsBridge(array $summary, array $raw, string $healthStatus): array
    {
        $lastAt = isset($summary['last_activity_at']) && is_string($summary['last_activity_at'])
            ? CarbonImmutable::parse($summary['last_activity_at'])
            : null;

        $inactive = $lastAt === null || $lastAt->lessThan(CarbonImmutable::now()->subDays(self::INACTIVE_DAYS));
        $lowActivity = $lastAt !== null
            && $lastAt->lessThan(CarbonImmutable::now()->subDays(self::WATCH_DAYS))
            && ! $inactive;

        return [
            'activity_status' => $healthStatus,
            'inactivity_flag' => $inactive || $lowActivity,
            'open_tickets' => (int) $raw['open_tickets'],
            'possible_risk_flag' => $healthStatus !== 'healthy',
        ];
    }
}
