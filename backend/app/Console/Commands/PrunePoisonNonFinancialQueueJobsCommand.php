<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Removes poisoned Redis queue payloads for non-financial housekeeping jobs only.
 *
 * Safety:
 * - Only deletes jobs whose JSON displayName is exactly ExpireInventoryReservationsJob or ExpireIdempotencyKeysJob.
 * - Scope is strictly: queues:low_priority (LIST) + queues:low_priority:reserved (ZSET) only.
 * - Does not touch queues:default:reserved, queues:high_priority*, or queues:low_priority:notify.
 * - Never uses FLUSHALL / broad queue purge.
 *
 * Run only while queue workers are stopped to avoid races.
 */
class PrunePoisonNonFinancialQueueJobsCommand extends Command
{
    protected $signature = 'queue:prune-poison-non-financial
                            {--apply : Actually remove payloads (default is dry-run)}
                            {--connection= : Redis connection name (default: redis default)}';

    protected $description = 'Targeted prune of ExpireInventoryReservationsJob / ExpireIdempotencyKeysJob from Redis queue lists and reserved zsets';

    /** @var list<string> */
    private const PRUNABLE_DISPLAY_NAMES = [
        'App\\Jobs\\ExpireInventoryReservationsJob',
        'App\\Jobs\\ExpireIdempotencyKeysJob',
    ];

    /** @var list<string> */
    private const LIST_KEYS = [
        'queues:low_priority',
    ];

    /** @var list<string> */
    private const ZSET_RESERVED_KEYS = [
        'queues:low_priority:reserved',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $connName = (string) ($this->option('connection') ?: config('queue.connections.redis.connection', 'default'));

        $this->info('Redis connection: '.$connName.' | apply='.($apply ? 'yes' : 'no (dry-run)'));
        $this->newLine();

        $this->line('--- Snapshot (logical keys; Laravel applies REDIS_PREFIX) ---');
        foreach (array_merge(self::LIST_KEYS, self::ZSET_RESERVED_KEYS) as $logicalKey) {
            $this->snapshotKey($connName, $logicalKey);
        }
        $this->newLine();

        if (! $apply) {
            $this->warn('Dry-run only. Re-run with --apply after stopping queue workers (queue_high, queue_default, queue_low).');

            return self::SUCCESS;
        }

        $removedLists = 0;
        $removedZsets = 0;

        foreach (self::LIST_KEYS as $logicalKey) {
            $removedLists += $this->pruneList($connName, $logicalKey);
        }
        foreach (self::ZSET_RESERVED_KEYS as $logicalKey) {
            $removedZsets += $this->pruneReservedZset($connName, $logicalKey);
        }

        $this->newLine();
        $this->info('Removed from lists: '.$removedLists);
        $this->info('Removed from reserved zsets: '.$removedZsets);
        $this->newLine();
        $this->line('--- Snapshot after prune ---');
        foreach (array_merge(self::LIST_KEYS, self::ZSET_RESERVED_KEYS) as $logicalKey) {
            $this->snapshotKey($connName, $logicalKey);
        }

        return self::SUCCESS;
    }

    private function snapshotKey(string $connName, string $logicalKey): void
    {
        $redis = Redis::connection($connName);
        $type = $redis->type($logicalKey);
        $typeStr = $this->redisTypeLabel($type);

        if ($typeStr === 'list') {
            $len = (int) $redis->lLen($logicalKey);
            $this->line(sprintf('LIST %-40s len=%d', $logicalKey, $len));
        } elseif ($typeStr === 'zset') {
            $card = (int) $redis->zCard($logicalKey);
            $this->line(sprintf('ZSET %-40s card=%d', $logicalKey, $card));
        } elseif ($typeStr === 'none') {
            $this->line(sprintf('MISS %-40s', $logicalKey));
        } else {
            $this->line(sprintf('TYPE %-40s %s', $logicalKey, $typeStr));
        }
    }

    private function pruneList(string $connName, string $logicalKey): int
    {
        $redis = Redis::connection($connName);
        if ($this->redisTypeLabel($redis->type($logicalKey)) !== 'list') {
            return 0;
        }

        $items = $redis->lRange($logicalKey, 0, -1);
        if (! is_array($items)) {
            return 0;
        }

        $kept = [];
        $removed = 0;
        foreach ($items as $payload) {
            if (! is_string($payload)) {
                continue;
            }
            if ($this->isPrunableHousekeepingPayload($payload)) {
                $removed++;
                continue;
            }
            $kept[] = $payload;
        }

        if ($removed === 0) {
            return 0;
        }

        $redis->del($logicalKey);
        foreach (array_chunk($kept, 500) as $chunk) {
            if ($chunk !== []) {
                $redis->rPush($logicalKey, ...$chunk);
            }
        }

        return $removed;
    }

    private function pruneReservedZset(string $connName, string $logicalKey): int
    {
        $redis = Redis::connection($connName);
        if ($this->redisTypeLabel($redis->type($logicalKey)) !== 'zset') {
            return 0;
        }

        // Reserved members are written by Laravel's queue Lua (cjson.encode) and may not byte-match
        // PHP's json_encode of the same logical payload — ZREM must use the exact Redis member string.
        $script = <<<'LUA'
local key = KEYS[1]
local vals = redis.call('ZRANGE', key, 0, -1)
local removed = 0
local t1, t2 = ARGV[1], ARGV[2]
for _, v in ipairs(vals) do
  local ok, data = pcall(cjson.decode, v)
  if ok and type(data) == 'table' and type(data['displayName']) == 'string' then
    if data['displayName'] == t1 or data['displayName'] == t2 then
      redis.call('ZREM', key, v)
      removed = removed + 1
    end
  end
end
return removed
LUA;

        return (int) $redis->eval(
            $script,
            1,
            $logicalKey,
            'App\\Jobs\\ExpireInventoryReservationsJob',
            'App\\Jobs\\ExpireIdempotencyKeysJob',
        );
    }

    private function isPrunableHousekeepingPayload(string $payload): bool
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return false;
        }
        $name = $data['displayName'] ?? null;

        return is_string($name) && in_array($name, self::PRUNABLE_DISPLAY_NAMES, true);
    }

    private function redisTypeLabel(mixed $type): string
    {
        // phpredis TYPE integers (stable across versions we target).
        if (is_int($type)) {
            return match ($type) {
                1 => 'string',
                2 => 'set',
                3 => 'list',
                4 => 'zset',
                5 => 'hash',
                0 => 'none',
                default => 'unknown('.$type.')',
            };
        }

        return 'unknown';
    }
}
