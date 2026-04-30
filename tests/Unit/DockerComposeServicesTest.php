<?php

use App\Concerns\InteractsWithDockerComposeServices;

// Harness that exposes the protected helpers without needing a full Command context
$makeServices = function (): object {
    return new class {
        use InteractsWithDockerComposeServices;

        // Satisfy trait dependency — not called in these tests
        public function warn(string $message): void {}

        public function testAddNetwork(array &$compose): void
        {
            $this->addRouterNetworkIfAbsent($compose);
        }

        public function testBackfill(array &$compose): void
        {
            $this->backfillRouterLabels($compose);
        }
    };
};

// ── addRouterNetworkIfAbsent ──────────────────────────────────────────────────

describe('addRouterNetworkIfAbsent', function () use ($makeServices) {
    it('adds fly-router network when absent', function () use ($makeServices) {
        $compose = ['networks' => ['fly' => ['driver' => 'bridge']]];
        $makeServices()->testAddNetwork($compose);

        expect($compose['networks'])->toHaveKey('fly-router');
        expect($compose['networks']['fly-router'])->toBe(['external' => true, 'name' => 'fly-router']);
    });

    it('does not overwrite an existing fly-router network entry', function () use ($makeServices) {
        $existing = ['external' => true, 'name' => 'fly-router'];
        $compose   = ['networks' => ['fly-router' => $existing]];
        $makeServices()->testAddNetwork($compose);

        expect($compose['networks']['fly-router'])->toBe($existing);
    });

    it('is idempotent when called twice', function () use ($makeServices) {
        $compose = ['networks' => []];
        $svc = $makeServices();
        $svc->testAddNetwork($compose);
        $svc->testAddNetwork($compose);

        expect(array_keys($compose['networks']))->toBe(['fly-router']);
    });

    it('creates the networks key when the compose has none', function () use ($makeServices) {
        $compose = [];
        $makeServices()->testAddNetwork($compose);

        expect($compose['networks']['fly-router']['external'])->toBeTrue();
    });
});

// ── backfillRouterLabels ──────────────────────────────────────────────────────

describe('backfillRouterLabels', function () use ($makeServices) {
    it('adds Traefik labels to laravel.fly when missing', function () use ($makeServices) {
        $compose = ['services' => ['laravel.fly' => ['networks' => ['fly']]]];
        $makeServices()->testBackfill($compose);

        expect($compose['services']['laravel.fly']['labels'])->not->toBeEmpty();
    });

    it('the backfilled labels include traefik.enable=true', function () use ($makeServices) {
        $compose = ['services' => ['laravel.fly' => ['networks' => ['fly']]]];
        $makeServices()->testBackfill($compose);

        expect($compose['services']['laravel.fly']['labels'])->toContain('traefik.enable=true');
    });

    it('the backfilled labels reference FLY_APP_HOST', function () use ($makeServices) {
        $compose = ['services' => ['laravel.fly' => ['networks' => ['fly']]]];
        $makeServices()->testBackfill($compose);

        $labels = implode("\n", $compose['services']['laravel.fly']['labels']);
        expect($labels)->toContain('FLY_APP_HOST');
    });

    it('does not overwrite existing labels', function () use ($makeServices) {
        $existing = ['my.custom.label=value'];
        $compose  = ['services' => ['laravel.fly' => ['labels' => $existing, 'networks' => ['fly']]]];
        $makeServices()->testBackfill($compose);

        expect($compose['services']['laravel.fly']['labels'])->toBe($existing);
    });

    it('adds fly-router to laravel.fly networks when missing', function () use ($makeServices) {
        $compose = ['services' => ['laravel.fly' => ['networks' => ['fly']]]];
        $makeServices()->testBackfill($compose);

        expect($compose['services']['laravel.fly']['networks'])->toContain('fly-router');
    });

    it('does not duplicate fly-router in networks', function () use ($makeServices) {
        $compose = ['services' => ['laravel.fly' => ['networks' => ['fly', 'fly-router']]]];
        $makeServices()->testBackfill($compose);

        expect(array_count_values($compose['services']['laravel.fly']['networks'])['fly-router'])->toBe(1);
    });

    it('does nothing when laravel.fly is not present', function () use ($makeServices) {
        $compose = ['services' => ['mysql' => []]];
        $before  = $compose;
        $makeServices()->testBackfill($compose);

        expect($compose)->toBe($before);
    });
});
