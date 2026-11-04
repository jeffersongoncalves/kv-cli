<?php

namespace App\Commands\Connections;

use App\Concerns\FormatsOutput;
use App\Services\ConnectionService;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'connections:list';

    protected $description = 'List saved Redis connection profiles';

    public function handle(ConnectionService $connections): int
    {
        $rows = [];

        foreach ($connections->all() as $connection) {
            $rows[] = [
                'name' => $connection->name,
                'host' => "{$connection->host}:{$connection->port}",
                'db' => (string) $connection->database,
                'tls' => $connection->tls ? 'yes' : 'no',
                'ssh' => $connection->sshHost ?? '-',
            ];
        }

        $this->renderTable(['name', 'host', 'db', 'tls', 'ssh'], $rows);

        return self::SUCCESS;
    }
}
