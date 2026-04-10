<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAnnouncementBanner;
use App\Models\User;
use Tests\TestCase;

class PlatformAnnouncementBannerTest extends TestCase
{
    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ];
    }

    private function validBannerBody(bool $enabled = false): array
    {
        return [
            'is_enabled'  => $enabled,
            'title'       => null,
            'message'     => $enabled ? 'نص تجريبي للإعلان' : null,
            'link_url'    => null,
            'link_text'   => null,
            'variant'     => 'promo',
            'dismissible' => true,
        ];
    }

    public function test_guest_can_read_public_platform_announcement_banner_without_auth(): void
    {
        $this->getJson('/api/v1/public/platform-announcement-banner')
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['enabled', 'dismiss_token']]);
    }

    public function test_authenticated_user_can_read_public_banner(): void
    {
        config(['saas.platform_admin_emails' => []]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $this->createActiveSubscription($company);
        $user = $this->createUser($company, $branch, 'owner');

        $this->getJson('/api/v1/platform/announcement-banner', $this->authHeaders($user))
            ->assertSuccessful()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_non_platform_user_cannot_read_admin_banner(): void
    {
        config(['saas.platform_admin_emails' => []]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $this->createActiveSubscription($company);
        $user = $this->createUser($company, $branch, 'owner');

        $this->getJson('/api/v1/platform/announcement-banner/admin', $this->authHeaders($user))
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_OPERATOR_REQUIRED');
    }

    public function test_non_platform_user_cannot_update_banner(): void
    {
        config(['saas.platform_admin_emails' => []]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $this->createActiveSubscription($company);
        $user = $this->createUser($company, $branch, 'owner');

        $this->putJson(
            '/api/v1/platform/announcement-banner',
            $this->validBannerBody(false),
            $this->authHeaders($user),
        )->assertStatus(403);
    }

    public function test_platform_operator_can_update_and_round_trip_admin_read(): void
    {
        config(['saas.platform_admin_emails' => ['ops@platform.example']]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $this->createActiveSubscription($company);
        $user = $this->createUser($company, $branch, 'owner', ['email' => 'ops@platform.example']);

        $body = [
            'is_enabled'  => true,
            'title'       => 'عنوان',
            'message'     => 'رسالة للمستخدمين',
            'link_url'    => '/subscription',
            'link_text'   => 'الاشتراك',
            'variant'     => 'info',
            'dismissible' => true,
        ];

        $put = $this->putJson('/api/v1/platform/announcement-banner', $body, $this->authHeaders($user));
        $put->assertSuccessful();

        $row = PlatformAnnouncementBanner::query()->first();
        $this->assertNotNull($row);
        $this->assertTrue($row->is_enabled);
        $this->assertSame('عنوان', $row->title);
        $this->assertSame('info', $row->variant);

        $this->getJson('/api/v1/platform/announcement-banner/admin', $this->authHeaders($user))
            ->assertSuccessful()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.message', 'رسالة للمستخدمين');
    }
}
