<?php

namespace Database\Seeders;

use App\Models\VerticalProfile;
use Illuminate\Database\Seeder;

class VerticalProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'code' => 'service_workshop',
                'name' => 'Service Workshop',
                'description' => 'Default profile for workshop-led operations.',
                'defaults' => ['booking.enabled' => true, 'fleet.portal.enabled' => true],
            ],
            [
                'code' => 'fleet_operations',
                'name' => 'Fleet Operations',
                'description' => 'Profile emphasizing fleet approvals and wallet controls.',
                'defaults' => ['fleet.portal.enabled' => true, 'approval.strict' => true],
            ],
            [
                'code' => 'retail_pos',
                'name' => 'Retail POS',
                'description' => 'Profile for counter/POS-heavy operations.',
                'defaults' => ['pos.quick_sale.enabled' => true, 'booking.enabled' => false],
            ],
        ];

        foreach ($profiles as $profile) {
            VerticalProfile::query()->updateOrCreate(
                ['code' => $profile['code']],
                $profile
            );
        }
    }
}
