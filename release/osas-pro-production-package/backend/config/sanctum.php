<?php

return [
    /**
     * Disable per-request last_used_at writes by default to avoid
     * authentication write amplification under peak API load.
     * Security is preserved via token expiration/revocation semantics.
     */
    'last_used_at' => filter_var((string) env('SANCTUM_UPDATE_LAST_USED_AT', 'false'), FILTER_VALIDATE_BOOL),
];

