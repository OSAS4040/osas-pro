<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Outcome of {@see \App\Actions\Auth\ResolveLoginEligibilityAction}.
 */
final class LoginEligibilityResult
{
    public const REASON_ACCOUNT_NOT_FOUND = 'ACCOUNT_NOT_FOUND';

    public const REASON_ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';

    public const REASON_ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';

    public const REASON_ACCOUNT_BLOCKED = 'ACCOUNT_BLOCKED';

    public const REASON_ACCOUNT_DISABLED = 'ACCOUNT_DISABLED';

    public const REASON_LOGIN_NOT_ALLOWED = 'LOGIN_NOT_ALLOWED';

    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reasonCode,
        public readonly ?string $messageKey,
        public readonly string $statusRaw,
        public readonly bool $isActive,
    ) {}

    public static function allowed(string $statusRaw, bool $isActive): self
    {
        return new self(true, null, null, $statusRaw, $isActive);
    }

    public static function denied(
        string $reasonCode,
        string $messageKey,
        string $statusRaw,
        bool $isActive,
    ): self {
        return new self(false, $reasonCode, $messageKey, $statusRaw, $isActive);
    }

    /**
     * @return array{allowed: bool, reason_code: ?string, message_key: ?string, status: string, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'allowed'      => $this->allowed,
            'reason_code'  => $this->reasonCode,
            'message_key'  => $this->messageKey,
            'status'       => $this->statusRaw,
            'is_active'    => $this->isActive,
        ];
    }

    public function resolvedMessage(string $locale = 'ar'): string
    {
        if ($this->allowed || $this->messageKey === null) {
            return '';
        }

        $locale = $locale === 'en' ? 'en' : 'ar';
        $messages = (array) config("auth_login_eligibility.messages.{$locale}", []);
        $text = $messages[$this->messageKey] ?? null;

        return is_string($text) && $text !== ''
            ? $text
            : (string) config('auth_login_eligibility.messages.ar.'.$this->messageKey, 'تسجيل الدخول غير مسموح.');
    }

    /**
     * @return array{message: string, reason_code: ?string, message_key: ?string, trace_id: mixed}
     */
    public function toForbiddenResponseBody(string $locale = 'ar'): array
    {
        return [
            'message'     => $this->resolvedMessage($locale),
            'reason_code' => $this->reasonCode,
            'message_key' => $this->messageKey,
            'trace_id'    => app('trace_id'),
        ];
    }
}
