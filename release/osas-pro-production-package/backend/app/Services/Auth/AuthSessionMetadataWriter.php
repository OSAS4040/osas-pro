<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\Auth\UserAgentSummarizer;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthSessionMetadataWriter
{
    public function apply(PersonalAccessToken $token, Request $request, string $authChannel): void
    {
        $ua = substr((string) $request->userAgent(), 0, 2000);

        $token->forceFill([
            'auth_channel'         => $authChannel,
            'ip_address'           => $request->ip(),
            'user_agent'           => $ua !== '' ? $ua : null,
            'user_agent_summary'   => UserAgentSummarizer::summarize($ua),
        ])->save();
    }
}
