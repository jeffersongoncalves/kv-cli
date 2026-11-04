<?php

namespace App\Commands\Connections;

use App\DTOs\Connection;
use App\Services\ConnectionService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class AddCommand extends Command
{
    protected $signature = 'connections:add
        {name? : Profile name (e.g. "cache")}
        {--host= : Redis host}
        {--port= : Redis port (default 6379)}
        {--db= : Database index (default 0)}
        {--username= : ACL username (Redis 6+, leave empty for password-only auth)}
        {--password= : Password}
        {--tls : Connect over TLS (rediss://)}
        {--ssh-host= : SSH bastion host to tunnel the connection through (leave empty to connect directly)}
        {--ssh-port= : SSH port (default 22)}
        {--ssh-username= : SSH username}
        {--ssh-private-key= : Path to the SSH private key (leave empty to use ssh-agent/default identity)}';

    protected $description = 'Save a named Redis connection profile';

    public function handle(ConnectionService $connections): int
    {
        $name = $this->argument('name') ?: text(label: 'Profile name', placeholder: 'cache', required: true);
        $host = $this->option('host') ?: text(label: 'Host', default: '127.0.0.1', required: true);
        $port = (int) ($this->option('port') ?: text(label: 'Port', default: '6379', required: true));
        $database = (int) ($this->option('db') ?? 0);
        $username = $this->option('username') ?: text(label: 'ACL username', hint: 'Leave empty for password-only auth');
        $password = $this->option('password') ?: password(label: 'Password', hint: 'Leave empty if the server has no auth');

        $sshHost = $this->option('ssh-host') ?: (
            confirm(label: 'Connect through an SSH tunnel?', default: false)
                ? text(label: 'SSH host', required: true)
                : null
        );

        $sshPort = null;
        $sshUsername = null;
        $sshPrivateKey = null;

        if ($sshHost) {
            $sshPort = (int) ($this->option('ssh-port') ?: text(label: 'SSH port', default: '22', required: true));
            $sshUsername = $this->option('ssh-username') ?: text(label: 'SSH username', required: true);
            $sshPrivateKey = $this->option('ssh-private-key') ?: text(
                label: 'SSH private key path',
                hint: 'Leave empty to use ssh-agent/default identity',
            );
        }

        $connections->save(new Connection(
            name: $name,
            host: $host,
            port: $port,
            database: $database,
            username: $username ?: null,
            password: $password ?: null,
            tls: (bool) $this->option('tls'),
            sshHost: $sshHost ?: null,
            sshPort: $sshPort,
            sshUsername: $sshUsername ?: null,
            sshPrivateKey: $sshPrivateKey ?: null,
        ));

        $this->components->info("Connection \"{$name}\" saved to {$connections->path()}");

        return self::SUCCESS;
    }
}
