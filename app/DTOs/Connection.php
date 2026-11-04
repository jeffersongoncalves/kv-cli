<?php

namespace App\DTOs;

class Connection
{
    public function __construct(
        public readonly string $name,
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 6379,
        public readonly int $database = 0,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly bool $tls = false,
        public readonly ?string $sshHost = null,
        public readonly ?int $sshPort = null,
        public readonly ?string $sshUsername = null,
        public readonly ?string $sshPrivateKey = null,
    ) {}

    public function withDatabase(int $database): self
    {
        return $this->copy(database: $database);
    }

    /**
     * Points the connection at a local port instead of its real host, used
     * once an SSH tunnel has forwarded that port to $this->host:$this->port.
     */
    public function withTunnel(int $localPort): self
    {
        return $this->copy(host: '127.0.0.1', port: $localPort);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            host: (string) ($data['host'] ?? '127.0.0.1'),
            port: (int) ($data['port'] ?? 6379),
            database: (int) ($data['database'] ?? 0),
            username: isset($data['username']) ? (string) $data['username'] : null,
            password: isset($data['password']) ? (string) $data['password'] : null,
            tls: (bool) ($data['tls'] ?? false),
            sshHost: isset($data['ssh_host']) ? (string) $data['ssh_host'] : null,
            sshPort: isset($data['ssh_port']) ? (int) $data['ssh_port'] : null,
            sshUsername: isset($data['ssh_username']) ? (string) $data['ssh_username'] : null,
            sshPrivateKey: isset($data['ssh_private_key']) ? (string) $data['ssh_private_key'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'tls' => $this->tls,
            'ssh_host' => $this->sshHost,
            'ssh_port' => $this->sshPort,
            'ssh_username' => $this->sshUsername,
            'ssh_private_key' => $this->sshPrivateKey,
        ];
    }

    private function copy(?string $host = null, ?int $port = null, ?int $database = null): self
    {
        return new self(
            name: $this->name,
            host: $host ?? $this->host,
            port: $port ?? $this->port,
            database: $database ?? $this->database,
            username: $this->username,
            password: $this->password,
            tls: $this->tls,
            sshHost: $this->sshHost,
            sshPort: $this->sshPort,
            sshUsername: $this->sshUsername,
            sshPrivateKey: $this->sshPrivateKey,
        );
    }
}
