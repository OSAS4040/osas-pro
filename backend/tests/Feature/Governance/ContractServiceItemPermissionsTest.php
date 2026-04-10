<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Contract;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * صلاحيات بنود الكتالوج التعاقدي مفصّلة في `config/permissions.php` — لا تعتمد على `users.update` فقط.
 */
final class ContractServiceItemPermissionsTest extends TestCase
{
    public function test_staff_cannot_list_contract_service_items_without_permission(): void
    {
        $t = $this->createTenant('staff');

        $contract = Contract::create([
            'uuid'       => Str::uuid(),
            'company_id' => $t['company']->id,
            'title'      => 'Perm test',
            'party_name' => 'X',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_by' => $t['user']->id,
        ]);

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/governance/contracts/'.$contract->id.'/service-items')
            ->assertStatus(403);
    }

    public function test_manager_can_list_contract_service_items(): void
    {
        $t = $this->createTenant('manager');

        $contract = Contract::create([
            'uuid'       => Str::uuid(),
            'company_id' => $t['company']->id,
            'title'      => 'Perm test M',
            'party_name' => 'Y',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_by' => $t['user']->id,
        ]);

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/governance/contracts/'.$contract->id.'/service-items')
            ->assertOk()
            ->assertJsonStructure(['data', 'trace_id']);
    }
}
