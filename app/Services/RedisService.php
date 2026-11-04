<?php

namespace App\Services;

use App\DTOs\Connection;
use Predis\Client;
use Predis\ClientInterface;

/**
 * Read-only by design: only SCAN/TYPE/TTL/INFO and per-type read commands
 * are ever sent. There is no passthrough for arbitrary commands, so this CLI
 * is safe to point at production.
 */
class RedisService
{
    private const SCAN_COUNT = 1000;

    public function __construct(private readonly SshTunnelService $tunnels = new SshTunnelService) {}

    public function connect(Connection $connection): ClientInterface
    {
        $target = $connection->sshHost !== null
            ? $connection->withTunnel($this->tunnels->open($connection))
            : $connection;

        $client = new Client($this->parameters($target));
        $client->connect();

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(Connection $connection): array
    {
        return array_filter([
            'scheme' => $connection->tls ? 'tls' : 'tcp',
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $connection->password,
        ], static fn (mixed $value) => $value !== null);
    }

    /**
     * Keys matching $pattern with their type and TTL (-1 = no expiry).
     *
     * @return list<array{key: string, type: string, ttl: int}>
     */
    public function keys(ClientInterface $redis, string $pattern, int $limit): array
    {
        $keys = $this->scanKeys($redis, $pattern, $limit);

        if ($keys === []) {
            return [];
        }

        $responses = $redis->pipeline(static function ($pipe) use ($keys): void {
            foreach ($keys as $key) {
                $pipe->type($key);
                $pipe->ttl($key);
            }
        });

        $rows = [];

        foreach ($keys as $i => $key) {
            $rows[] = ['key' => $key, 'type' => (string) $responses[$i * 2], 'ttl' => (int) $responses[$i * 2 + 1]];
        }

        return $rows;
    }

    /**
     * SCAN (never KEYS, which blocks the server) until $limit keys are found
     * or the keyspace is exhausted.
     *
     * @return list<string>
     */
    public function scanKeys(ClientInterface $redis, string $pattern, int $limit): array
    {
        $found = [];
        $cursor = 0;

        do {
            [$cursor, $batch] = $redis->scan((int) $cursor, ['MATCH' => $pattern, 'COUNT' => self::SCAN_COUNT]);

            foreach ($batch as $key) {
                $found[(string) $key] = true; // SCAN may return a key more than once

                if (count($found) >= $limit) {
                    break 2;
                }
            }
        } while ((int) $cursor !== 0);

        return array_map('strval', array_keys($found));
    }

    /**
     * Value of $key shaped as rows, reading at most $limit elements of a
     * collection so a huge hash/list/zset never floods the output.
     *
     * @return array{type: string, ttl: int, size: int, rows: list<array<string, mixed>>}
     */
    public function get(ClientInterface $redis, string $key, int $limit): array
    {
        $type = (string) $redis->type($key);
        $ttl = (int) $redis->ttl($key);

        [$size, $rows] = match ($type) {
            'none' => [0, []],
            'string' => [(int) $redis->strlen($key), [['value' => $redis->get($key)]]],
            'hash' => [(int) $redis->hlen($key), $this->pairs($redis->hscan($key, 0, ['COUNT' => $limit])[1], 'field', 'value', $limit)],
            'list' => [(int) $redis->llen($key), $this->indexed($redis->lrange($key, 0, $limit - 1))],
            'set' => [(int) $redis->scard($key), $this->members($redis->sscan($key, 0, ['COUNT' => $limit])[1], $limit)],
            'zset' => [(int) $redis->zcard($key), $this->pairs($redis->zrange($key, 0, $limit - 1, ['withscores' => true]), 'member', 'score', $limit)],
            'stream' => [(int) $redis->xlen($key), $this->pairs($redis->xrange($key, '-', '+', $limit), 'id', 'fields', $limit)],
            default => [0, [['value' => "(unsupported type: {$type})"]]],
        };

        return ['type' => $type, 'ttl' => $ttl, 'size' => $size, 'rows' => $rows];
    }

    /**
     * INFO as section/key/value rows.
     *
     * @return list<array{section: string, key: string, value: mixed}>
     */
    public function info(ClientInterface $redis, ?string $section = null): array
    {
        $info = $section !== null ? $redis->info($section) : $redis->info();
        $rows = [];

        foreach ($info as $name => $values) {
            foreach (is_array($values) ? $values : [$name => $values] as $key => $value) {
                $rows[] = ['section' => strtolower((string) $name), 'key' => (string) $key, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int|string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function pairs(array $map, string $keyName, string $valueName, int $limit): array
    {
        $rows = [];

        foreach (array_slice($map, 0, $limit, true) as $key => $value) {
            $rows[] = [$keyName => (string) $key, $valueName => $value];
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function indexed(array $values): array
    {
        return array_map(static fn (int $i, mixed $v) => ['index' => $i, 'value' => $v], array_keys($values), $values);
    }

    /**
     * @param  list<mixed>  $members
     * @return list<array<string, mixed>>
     */
    private function members(array $members, int $limit): array
    {
        return array_map(static fn (mixed $m) => ['member' => $m], array_slice($members, 0, $limit));
    }
}
