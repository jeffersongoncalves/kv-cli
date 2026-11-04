<?php

namespace App\Exceptions;

use RuntimeException;

class ConnectionNotFoundException extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self("Connection \"{$name}\" not found. Run `kv connections:add` or `kv connections:list`.");
    }
}
