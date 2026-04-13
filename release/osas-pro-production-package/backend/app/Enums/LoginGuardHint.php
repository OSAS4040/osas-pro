<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Hint for which SPA guard / portal the client should prefer after login.
 */
enum LoginGuardHint: string
{
    case Platform = 'platform';

    case Staff = 'staff';

    case Fleet = 'fleet';

    case Customer = 'customer';

    case Onboarding = 'onboarding';

    case Unknown = 'unknown';
}
