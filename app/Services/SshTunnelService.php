<?php

namespace App\Services;

use App\DTOs\Connection;
use App\Exceptions\SshTunnelException;
use Symfony\Component\Process\Process;

class SshTunnelService
{
    private const CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Opens a local port forward to $connection's real host:port through its
     * SSH config, using the system `ssh` client. The tunnel process is kept
     * alive for the lifetime of the script and stopped on shutdown.
     *
     * @return int the local port the client should connect to instead
     */
    public function open(Connection $connection): int
    {
        $localPort = $this->freeLocalPort();

        $targetHost = $connection->dockerContainer !== null
            ? $this->containerIp($connection)
            : $connection->host;

        $process = new Process($this->command($connection, $localPort, $targetHost));
        $process->setTimeout(null);
        $process->start();

        register_shutdown_function(static function () use ($process): void {
            if ($process->isRunning()) {
                $process->stop();
            }
        });

        $this->waitUntilOpen($process, $localPort, $connection->name);

        return $localPort;
    }

    /**
     * Builds the `ssh -L ...` argv for forwarding $localPort to the
     * connection's real host:port (or $targetHost, e.g. a container IP)
     * through its SSH bastion.
     *
     * @return list<string>
     */
    public function command(Connection $connection, int $localPort, ?string $targetHost = null): array
    {
        $targetHost ??= $connection->host;

        return [
            'ssh', '-N',
            '-L', "{$localPort}:{$targetHost}:{$connection->port}",
            ...$this->sshOptions($connection),
        ];
    }

    /**
     * Builds the `ssh ... docker inspect` argv that prints the IP(s) of the
     * connection's Docker container, space-separated, one per network.
     *
     * @return list<string>
     */
    public function inspectCommand(Connection $connection): array
    {
        $format = '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}';

        return [
            'ssh',
            ...$this->sshOptions($connection),
            'docker inspect -f '.self::shellQuote($format).' '.self::shellQuote((string) $connection->dockerContainer),
        ];
    }

    /**
     * Resolves the container's current IP over SSH, so the tunnel survives
     * container recreation (Docker hands out a new IP each time).
     */
    private function containerIp(Connection $connection): string
    {
        $container = (string) $connection->dockerContainer;

        $process = new Process($this->inspectCommand($connection));
        $process->setTimeout(self::CONNECT_TIMEOUT_SECONDS * 2);
        $process->run();

        if (! $process->isSuccessful()) {
            throw SshTunnelException::containerNotResolved($connection->name, $container, $process->getErrorOutput());
        }

        // ponytail: first network wins; add a docker_network option if a container on several networks picks the wrong one
        $ip = strtok(trim($process->getOutput()), " \n");

        if ($ip === false) {
            throw SshTunnelException::containerNotResolved($connection->name, $container, 'container has no IP address (network_mode: host?)');
        }

        return $ip;
    }

    /**
     * Shared `-p/-o/-i user@host` options for every ssh invocation.
     *
     * @return list<string>
     */
    private function sshOptions(Connection $connection): array
    {
        $options = [
            '-p', (string) ($connection->sshPort ?? 22),
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ExitOnForwardFailure=yes',
        ];

        if ($connection->sshPrivateKey !== null) {
            $options[] = '-i';
            $options[] = $connection->sshPrivateKey;
        }

        $options[] = $connection->sshUsername !== null
            ? "{$connection->sshUsername}@{$connection->sshHost}"
            : (string) $connection->sshHost;

        return $options;
    }

    /**
     * POSIX single-quoting for the remote shell (escapeshellarg() follows the
     * local OS, which is wrong on Windows).
     */
    private static function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    // ponytail: bind-then-close to find a free port; a rival process could grab it
    // before ssh binds. Upgrade to a retry loop if this ever proves flaky in practice.
    private function freeLocalPort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new SshTunnelException("Could not allocate a local port for the SSH tunnel: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitUntilOpen(Process $process, int $localPort, string $connectionName): void
    {
        $deadline = microtime(true) + self::CONNECT_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            if (! $process->isRunning()) {
                throw SshTunnelException::failed($connectionName, $process->getErrorOutput());
            }

            $socket = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 0.2);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }

        $process->stop();

        throw SshTunnelException::timedOut($connectionName);
    }
}
