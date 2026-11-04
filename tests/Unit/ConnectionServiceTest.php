<?php

use App\DTOs\Connection;
use App\Services\ConnectionService;

beforeEach(function () {
    $this->originalHome = getenv('HOME');
    $this->tempHome = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('kv-cli-test-');
    mkdir($this->tempHome);
    putenv('HOME='.$this->tempHome);
});

afterEach(function () {
    putenv($this->originalHome === false ? 'HOME' : "HOME={$this->originalHome}");
});

it('saves and reloads a connection profile', function () {
    (new ConnectionService)->save(new Connection(
        name: 'cache',
        host: 'redis.internal',
        port: 6380,
        database: 2,
        username: 'app',
        password: 'secret',
        tls: true,
        sshHost: 'bastion.example.com',
        sshUsername: 'deploy',
    ));

    $reloaded = (new ConnectionService)->getOrFail('cache');

    expect($reloaded->host)->toBe('redis.internal')
        ->and($reloaded->port)->toBe(6380)
        ->and($reloaded->database)->toBe(2)
        ->and($reloaded->username)->toBe('app')
        ->and($reloaded->password)->toBe('secret')
        ->and($reloaded->tls)->toBeTrue()
        ->and($reloaded->sshHost)->toBe('bastion.example.com')
        ->and($reloaded->sshUsername)->toBe('deploy');
});

it('removes a connection profile', function () {
    $connections = new ConnectionService;
    $connections->save(new Connection(name: 'cache'));

    expect($connections->forget('cache'))->toBeTrue()
        ->and($connections->forget('cache'))->toBeFalse()
        ->and($connections->get('cache'))->toBeNull();
});

it('derives connections for another database index and a tunnel port', function () {
    $base = new Connection(name: 'cache', host: 'redis.internal', password: 'secret');

    expect($base->withDatabase(3)->database)->toBe(3)
        ->and($base->withDatabase(3)->password)->toBe('secret')
        ->and($base->withTunnel(54321)->host)->toBe('127.0.0.1')
        ->and($base->withTunnel(54321)->port)->toBe(54321);
});
