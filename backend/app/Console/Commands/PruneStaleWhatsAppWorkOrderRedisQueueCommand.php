<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Removes only NotifyCustomerWorkOrderWhatsAppJob payloads from Redis queue structures on "default".
 *
 * Safety:
 * - displayName must be exactly App\Jobs\NotifyCustomerWorkOrderWhatsAppJob (JSON-decoded).
 * - Keys: queues:default (LIST), queues:default:reserved (ZSET), queues:default:delayed (ZSET if present).
 * - No FLUSHALL, no broad purge, no other queues.
 *
 * Run while queue workers touching "default" are stopped to avoid races.
 */
class PruneStaleWhatsAppWorkOrderRedisQueueCommand extends Command
{
    private const DISPLAY_NAME = 'App\\Jobs\\NotifyCustomerWorkOrderWhatsAppJob';

    /** @var list<string> */
    private const LIST_KEYS = [
        'queues:default',
    ];

    /** @var list<string> */
    private const ZSET_KEYS = [
        'queues:default:reserved',
        'queues:default:delayed',
    ];

    protected $signature = 'queue:prune-stale-whatsapp-work-order-jobs
                            {--apply : Actually remove payloads (default is dry-run)}
                            {--connection= : Redis connection name (default: queue redis connection)}';

    protected $description = 'Targeted prune of NotifyCustomerWorkOrderWhatsAppJob from default queue list, reserved, and delayed zsets';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $connName = (string) ($this->option('connection') ?: config('queue.connections.redis.connection', 'default'));

        $this->info('Redis connection: '.$connName.' | apply='.($apply ? 'yes' : 'no (dry-run)'));
        $this->newLine();

        $this->line('--- Snapshot ---');
        foreach (array_merge(self::LIST_KEYS, self::ZSET_KEYS) as $logicalKey) {
            $this->snapshotKey($connName, $logicalKey);
        }
        $this->newLine();

        $wouldRemoveList = $this->countRemovableInLists($connName);
        $wouldRemoveZset = $this->countRemovableInZsets($connName);
        $this->info('Dry-run removable (approx): list payloads='.$wouldRemoveList.', zset members='.$wouldRemoveZset);

        if (! $apply) {
            $this->warn('Dry-run only. Re-run with --apply after stopping workers that consume the "default" queue.');

            return self::SUCCESS;
        }

        $removedLists = 0;
        $removedZsets = 0;

        foreach (self::LIST_KEYS as $logicalKey) {
            $removedLists += $this->pruneList($connName, $logicalKey);
        }
        foreach (self::ZSET_KEYS as $logicalKey) {
            $removedZsets += $this->pruneZsetByDisplayName($connName, $logicalKey);
        }

        $this->newLine();
        $this->info('Removed from lists: '.$removedLists);
        $this->info('Removed from zsets: '.$removedZsets);
        $this->newLine();
        $this->line('--- Snapshot after prune ---');
        foreach (array_merge(self::LIST_KEYS, self::ZSET_KEYS) as $logicalKey) {
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
            $this->line(sprintf('LIST %-42s len=%d', $logicalKey, $len));
        } elseif ($typeStr === 'zset') {
            $card = (int) $redis->zCard($logicalKey);
            $this->line(sprintf('ZSET %-42s card=%d', $logicalKey, $card));
        } elseif ($typeStr === 'none') {
            $this->line(sprintf('MISS %-42s', $logicalKey));
        } else {
            $this->line(sprintf('TYPE %-42s %s', $logicalKey, $typeStr));
        }
    }

    private function countRemovableInLists(string $connName): int
    {
        $redis = Redis::connection($connName);
        $total = 0;
        foreach (self::LIST_KEYS as $logicalKey) {
            if ($this->redisTypeLabel($redis->type($logicalKey)) !== 'list') {
                continue;
            }
            $items = $redis->lRange($logicalKey, 0, -1);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $payload) {
                if (is_string($payload) && $this->isWhatsAppWorkOrderPayload($payload)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    private function countRemovableInZsets(string $connName): int
    {
        $redis = Redis::connection($connName);
        $total = 0;
        foreach (self::ZSET_KEYS as $logicalKey) {
            if ($this->redisTypeLabel($redis->type($logicalKey)) !== 'zset') {
                continue;
            }
            $items = $redis->zRange($logicalKey, 0, -1);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $member) {
                if (is_string($member) && $this->isWhatsAppWorkOrderPayload($member)) {
                    $total++;
                }
            }
        }

        return $total;
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
            if ($this->isWhatsAppWorkOrderPayload($payload)) {
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

    private function pruneZsetByDisplayName(string $connName, string $logicalKey): int
    {
        $redis = Redis::connection($connName);
        if ($this->redisTypeLabel($redis->type($logicalKey)) !== 'zset') {
            return 0;
        }

        $script = <<<'LUA'
local key = KEYS[1]
local target = ARGV[1]
local vals = redis.call('ZRANGE', key, 0, -1)
local removed = 0
for _, v in ipairs(vals) do
  local ok, data = pcall(cjson.decode, v)
  if ok and type(data) == 'table' and type(data['displayName']) == 'string' and data['displayName'] == target then
    redis.call('ZREM', key, v)
    removed = removed + 1
  end
end
return removed
LUA;

        return (int) $redis->eval($script, 1, $logicalKey, self::DISPLAY_NAME);
    }

    private function isWhatsAppWorkOrderPayload(string $payload): bool
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return false;
        }
        $name = $data['displayName'] ?? null;

        return is_string($name) && $name === self::DISPLAY_NAME;
    }

    private function redisTypeLabel(mixed $type): string
    {
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
