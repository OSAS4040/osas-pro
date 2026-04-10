param(
  [string]$RedisContainer = "saas_redis",
  [string]$RedisPassword = "redis_password",
  [int]$RedisDb = 0
)

$ErrorActionPreference = "Stop"

$queueKey = "saas_pos_erp_queues:high_priority"
$reservedKey = "saas_pos_erp_queues:high_priority:reserved"
$delayedKey = "saas_pos_erp_queues:high_priority:delayed"
$needle = "PostPosLedgerJob"

$beforeQueue = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw LLEN $queueKey"
$beforeReserved = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZCARD $reservedKey"
$beforeDelayed = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZCARD $delayedKey"

$removedQueue = 0
$queueItems = @(docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw LRANGE $queueKey 0 -1")
foreach ($item in $queueItems) {
  if ($item -like "*$needle*") {
    $delta = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw LREM $queueKey 0 '$item'"
    $removedQueue += [int]$delta
  }
}

$removedReserved = 0
$reservedItems = @(docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZRANGE $reservedKey 0 -1")
foreach ($item in $reservedItems) {
  if ($item -like "*$needle*") {
    $delta = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZREM $reservedKey '$item'"
    $removedReserved += [int]$delta
  }
}

$removedDelayed = 0
$delayedItems = @(docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZRANGE $delayedKey 0 -1")
foreach ($item in $delayedItems) {
  if ($item -like "*$needle*") {
    $delta = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZREM $delayedKey '$item'"
    $removedDelayed += [int]$delta
  }
}

$afterQueue = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw LLEN $queueKey"
$afterReserved = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZCARD $reservedKey"
$afterDelayed = docker exec $RedisContainer sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli -n $RedisDb --raw ZCARD $delayedKey"

Write-Host "BEFORE queue=$beforeQueue reserved=$beforeReserved delayed=$beforeDelayed"
Write-Host "REMOVED queue=$removedQueue reserved=$removedReserved delayed=$removedDelayed"
Write-Host "AFTER  queue=$afterQueue reserved=$afterReserved delayed=$afterDelayed"
