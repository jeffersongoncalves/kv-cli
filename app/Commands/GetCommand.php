<?php

namespace App\Commands;

use App\Concerns\FormatsOutput;
use App\Services\ConnectionService;
use App\Services\RedisService;
use LaravelZero\Framework\Commands\Command;

class GetCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'get {connection : Saved connection profile name}
        {key : Key to read}
        {--db= : Override the profile\'s database index}
        {--limit=100 : Maximum number of elements to read from a hash/list/set/zset/stream}
        {--format=table : table|json|csv}';

    protected $description = 'Read a key of any type (string, hash, list, set, zset, stream)';

    public function handle(ConnectionService $connections, RedisService $redis): int
    {
        $connection = $connections->getOrFail((string) $this->argument('connection'));

        if ($this->option('db') !== null) {
            $connection = $connection->withDatabase((int) $this->option('db'));
        }

        $key = (string) $this->argument('key');
        $result = $redis->get($redis->connect($connection), $key, max(1, (int) $this->option('limit')));

        if ($result['type'] === 'none') {
            $this->components->error("Key \"{$key}\" does not exist.");

            return self::FAILURE;
        }

        $format = (string) $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode(['key' => $key] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');

            return self::SUCCESS;
        }

        if ($format === 'table') {
            $this->components->twoColumnDetail('type', $result['type']);
            $this->components->twoColumnDetail('ttl', (string) $result['ttl']);
            $this->components->twoColumnDetail('size', (string) $result['size']);
        }

        $this->renderRows($result['rows'], $format);

        return self::SUCCESS;
    }
}
