<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-company-{companyId}', function ($user, int $companyId): bool {
    return (int) ($user->company_id ?? 0) === $companyId;
});

Broadcast::channel('private-admin', function ($user): bool {
    return (bool) ($user->is_platform_user ?? false);
});

