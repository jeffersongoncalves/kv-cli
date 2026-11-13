<?php

namespace App\Exceptions;

use RuntimeException;

class SshTunnelException extends RuntimeException
{
    public static function failed(string $connectionName, string $errorOutput): self
    {
        $reason = trim($errorOutput) !== '' ? trim($errorOutput) : 'ssh exited unexpectedly';

        return new self("SSH tunnel for \"{$connectionName}\" failed: {$reason}");
    }

    public static function containerNotResolved(string $connectionName, string $container, string $errorOutput): self
    {
        $reason = trim($errorOutput) !== '' ? trim($errorOutput) : 'ssh exited unexpectedly';

        return new self("Could not resolve the IP of Docker container \"{$container}\" for \"{$connectionName}\": {$reason}. "
            .'Check the container name and that the SSH user can run `docker` (is the user in the docker group?).');
    }

    public static function timedOut(string $connectionName): self
    {
        return new self("SSH tunnel for \"{$connectionName}\" timed out waiting for the forwarded port to open.");
    }
}
