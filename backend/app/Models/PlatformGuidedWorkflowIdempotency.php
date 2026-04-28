<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $idempotency_key
 * @property string $incident_key
 * @property string $workflow_key
 * @property int $actor_user_id
 * @property string $status
 * @property int $http_status
 * @property array<string, mixed> $response_json
 */
final class PlatformGuidedWorkflowIdempotency extends Model
{
    protected $table = 'platform_guided_workflow_idempotency';

    public const UPDATED_AT = null;

    protected $fillable = [
        'idempotency_key',
        'incident_key',
        'workflow_key',
        'actor_user_id',
        'status',
        'http_status',
        'response_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'http_status' => 'integer',
        ];
    }
}
