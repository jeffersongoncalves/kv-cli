<?php

namespace App\Commands;

use App\Concerns\FormatsOutput;
use App\Services\ConnectionService;
use App\Services\RedisService;
use LaravelZero\Framework\Commands\Command;

class InfoCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'info {connection : Saved connection profile name}
        {section? : INFO section (server, memory, keyspace, stats, ...). Omit for all}
        {--format=table : table|json|csv}';

    protected $description = 'Show server INFO (version, memory, keyspace, ...)';

    public function handle(ConnectionService $connections, RedisService $redis): int
    {
        $connection = $connections->getOrFail((string) $this->argument('connection'));
        $section = $this->argument('section');

        $rows = $redis->info($redis->connect($connection), $section !== null ? (string) $section : null);

        $this->renderRows($rows, (string) $this->option('format'));

        return self::SUCCESS;
    }
}
