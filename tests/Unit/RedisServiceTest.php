<?php

use App\DTOs\Connection;
use App\Services\RedisService;
use Predis\ClientInterface;

it('builds predis parameters, omitting empty auth and switching scheme for tls', function () {
    $service = new RedisService;

    expect($service->parameters(new Connection(name: 'a')))->toBe([
        'scheme' => 'tcp', 'host' => '127.0.0.1', 'port' => 6379, 'database' => 0,
    ])->and($service->parameters(new Connection(name: 'a', username: 'u', password: 'p', tls: true)))->toBe([
        'scheme' => 'tls', 'host' => '127.0.0.1', 'port' => 6379, 'database' => 0, 'username' => 'u', 'password' => 'p',
    ]);
});

it('scans across cursors, dedupes and stops at the limit', function () {
    $redis = Mockery::mock(ClientInterface::class);
    $redis->shouldReceive('scan')->with(0, Mockery::any())->once()->andReturn(['7', ['a', 'b']]);
    $redis->shouldReceive('scan')->with(7, Mockery::any())->once()->andReturn(['9', ['b', 'c', 'd']]);

    expect((new RedisService)->scanKeys($redis, '*', 3))->toBe(['a', 'b', 'c']);
});

it('stops scanning when the cursor returns to zero', function () {
    $redis = Mockery::mock(ClientInterface::class);
    $redis->shouldReceive('scan')->once()->andReturn(['0', ['a']]);

    expect((new RedisService)->scanKeys($redis, '*', 100))->toBe(['a']);
});

it('flattens INFO sections into rows', function () {
    $redis = Mockery::mock(ClientInterface::class);
    $redis->shouldReceive('info')->once()->andReturn([
        'Server' => ['redis_version' => '7.2.4'],
        'Keyspace' => ['db0' => ['keys' => '10', 'expires' => '2']],
    ]);

    expect((new RedisService)->info($redis))->toBe([
        ['section' => 'server', 'key' => 'redis_version', 'value' => '7.2.4'],
        ['section' => 'keyspace', 'key' => 'db0', 'value' => ['keys' => '10', 'expires' => '2']],
    ]);
});
