<?php

namespace App\Commands;

use App\Concerns\FormatsOutput;
use App\Services\ConnectionService;
use App\Services\RedisService;
use LaravelZero\Framework\Commands\Command;

class KeysCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'keys {connection : Saved connection profile name}
        {pattern? : Glob pattern (e.g. "laravel_cache:*"), default *}
        {--db= : Override the profile\'s database index}
        {--limit=100 : Maximum number of keys to return}
        {--format=table : table|json|csv}';

    protected $description = 'List keys matching a pattern (via SCAN) with their type and TTL';

    public function handle(ConnectionService $connections, RedisService $redis): int
    {
        $connection = $connections->getOrFail((string) $this->argument('connection'));

        if ($this->option('db') !== null) {
            $connection = $connection->withDatabase((int) $this->option('db'));
        }

        $rows = $redis->keys(
            $redis->connect($connection),
            (string) ($this->argument('pattern') ?? '*'),
            max(1, (int) $this->option('limit')),
        );

        $this->renderRows($rows, (string) $this->option('format'));

        return self::SUCCESS;
    }
}
