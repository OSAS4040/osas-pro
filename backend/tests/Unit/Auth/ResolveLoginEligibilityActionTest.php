<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Actions\Auth\ResolveLoginEligibilityAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\LoginEligibilityResult;
use Tests\TestCase;

class ResolveLoginEligibilityActionTest extends TestCase
{
    private ResolveLoginEligibilityAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResolveLoginEligibilityAction;
    }

    public function test_null_user_is_not_found(): void
    {
        $r = ($this->action)(null);
        $this->assertFalse($r->allowed);
        $this->assertSame(LoginEligibilityResult::REASON_ACCOUNT_NOT_FOUND, $r->reasonCode);
    }

    public function test_active_and_is_active_true_is_allowed(): void
    {
        $user = new User([
            'status'    => UserStatus::Active,
            'is_active' => true,
        ]);
        $user->syncOriginal();

        $r = ($this->action)($user);
        $this->assertTrue($r->allowed);
        $this->assertNull($r->reasonCode);
    }

    public function test_blocked_takes_precedence_over_disabled_flag(): void
    {
        $user = new User([
            'status'    => UserStatus::Blocked,
            'is_active' => false,
        ]);
        $user->syncOriginal();

        $r = ($this->action)($user);
        $this->assertFalse($r->allowed);
        $this->assertSame(LoginEligibilityResult::REASON_ACCOUNT_BLOCKED, $r->reasonCode);
    }

    public function test_suspended(): void
    {
        $user = new User(['status' => UserStatus::Suspended, 'is_active' => true]);
        $user->syncOriginal();

        $r = ($this->action)($user);
        $this->assertSame(LoginEligibilityResult::REASON_ACCOUNT_SUSPENDED, $r->reasonCode);
    }

    public function test_inactive_even_when_is_active_true(): void
    {
        $user = new User(['status' => UserStatus::Inactive, 'is_active' => true]);
        $user->syncOriginal();

        $r = ($this->action)($user);
        $this->assertSame(LoginEligibilityResult::REASON_ACCOUNT_INACTIVE, $r->reasonCode);
    }

    public function test_active_but_is_active_false_is_account_disabled(): void
    {
        $user = new User(['status' => UserStatus::Active, 'is_active' => false]);
        $user->syncOriginal();

        $r = ($this->action)($user);
        $this->assertSame(LoginEligibilityResult::REASON_ACCOUNT_DISABLED, $r->reasonCode);
    }

}
