<?php

use Symfony\Component\Yaml\Yaml;

$projectRoot = dirname(__DIR__, 2);
$stubPath = fn (string $name) => "{$projectRoot}/resources/stubs/{$name}.stub";
$parseStub = fn (string $name) => Yaml::parseFile($stubPath($name));

// ── docker-compose.stub ───────────────────────────────────────────────────────

describe('docker-compose.stub', function () use ($parseStub) {
    it('has fly-router as an external network', function () use ($parseStub) {
        $compose = $parseStub('docker-compose');
        expect($compose['networks'])->toHaveKey('fly-router');
        expect($compose['networks']['fly-router']['external'])->toBeTrue();
        expect($compose['networks']['fly-router']['name'])->toBe('fly-router');
    });

    it('connects laravel.fly to fly-router', function () use ($parseStub) {
        $compose = $parseStub('docker-compose');
        expect($compose['services']['laravel.fly']['networks'])->toContain('fly-router');
    });

    it('enables Traefik on laravel.fly', function () use ($parseStub) {
        $labels = $parseStub('docker-compose')['services']['laravel.fly']['labels'];
        expect(implode("\n", $labels))->toContain('traefik.enable=true');
    });

    it('routes laravel.fly using FLY_APP_HOST', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('docker-compose')['services']['laravel.fly']['labels']);
        expect($labels)->toContain('FLY_APP_HOST');
    });

    it('sets the app loadbalancer port to 80', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('docker-compose')['services']['laravel.fly']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=80');
    });
});

// ── mailpit.stub ──────────────────────────────────────────────────────────────

describe('mailpit.stub', function () use ($parseStub) {
    it('connects to fly-router', function () use ($parseStub) {
        expect($parseStub('mailpit')['mailpit']['networks'])->toContain('fly-router');
    });

    it('routes via mailpit subdomain', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('mailpit')['mailpit']['labels']);
        expect($labels)->toContain('mailpit.');
    });

    it('points the loadbalancer at port 8025', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('mailpit')['mailpit']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=8025');
    });
});

// ── minio.stub ────────────────────────────────────────────────────────────────

describe('minio.stub', function () use ($parseStub) {
    it('connects to fly-router', function () use ($parseStub) {
        expect($parseStub('minio')['minio']['networks'])->toContain('fly-router');
    });

    it('routes via minio subdomain', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('minio')['minio']['labels']);
        expect($labels)->toContain('minio.');
    });

    it('points the loadbalancer at the console port 8900', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('minio')['minio']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=8900');
    });
});

// ── meilisearch.stub ──────────────────────────────────────────────────────────

describe('meilisearch.stub', function () use ($parseStub) {
    it('connects to fly-router', function () use ($parseStub) {
        expect($parseStub('meilisearch')['meilisearch']['networks'])->toContain('fly-router');
    });

    it('routes via meilisearch subdomain', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('meilisearch')['meilisearch']['labels']);
        expect($labels)->toContain('meilisearch.');
    });

    it('points the loadbalancer at port 7700', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('meilisearch')['meilisearch']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=7700');
    });
});

// ── typesense.stub ────────────────────────────────────────────────────────────

describe('typesense.stub', function () use ($parseStub) {
    it('connects to fly-router', function () use ($parseStub) {
        expect($parseStub('typesense')['typesense']['networks'])->toContain('fly-router');
    });

    it('routes via typesense subdomain', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('typesense')['typesense']['labels']);
        expect($labels)->toContain('typesense.');
    });

    it('points the loadbalancer at port 8108', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('typesense')['typesense']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=8108');
    });
});

// ── soketi.stub ───────────────────────────────────────────────────────────────

describe('soketi.stub', function () use ($parseStub) {
    it('connects to fly-router', function () use ($parseStub) {
        expect($parseStub('soketi')['soketi']['networks'])->toContain('fly-router');
    });

    it('routes via soketi subdomain', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('soketi')['soketi']['labels']);
        expect($labels)->toContain('soketi.');
    });

    it('points the loadbalancer at port 6001', function () use ($parseStub) {
        $labels = implode("\n", $parseStub('soketi')['soketi']['labels']);
        expect($labels)->toContain('loadbalancer.server.port=6001');
    });
});

// ── stateful-only services must NOT have Traefik labels ───────────────────────

it('mysql stub has no traefik labels', function () use ($parseStub) {
    expect($parseStub('mysql')['mysql'])->not->toHaveKey('labels');
});

it('redis stub has no traefik labels', function () use ($parseStub) {
    expect($parseStub('redis')['redis'])->not->toHaveKey('labels');
});

it('pgsql stub has no traefik labels', function () use ($parseStub) {
    expect($parseStub('pgsql')['pgsql'])->not->toHaveKey('labels');
});
