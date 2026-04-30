<?php

use App\Concerns\ManagesRouter;

// Concrete harness that exposes trait methods under test
$makeRouter = function (string $home = '/tmp/fly-test-home'): object {
    return new class($home) {
        use ManagesRouter;

        public function __construct(private string $fakeHome) {}

        protected function routerHome(): string
        {
            return $this->fakeHome.'/.fly/router';
        }

        public function exposeHome(): string           { return $this->routerHome(); }
        public function exposePath(): string           { return $this->routerComposePath(); }
        public function exposeDomain(): string         { return $this->routerDomain(); }
        public function exposeSanitise(string $n): string { return $this->sanitiseHostname($n); }
        public function exposeContents(): string       { return $this->routerComposeContents(); }
    };
};

// ── routerHome / routerComposePath ────────────────────────────────────────────

it('derives router home from HOME env', function () {
    putenv('HOME=/home/testuser');
    $router = new class {
        use ManagesRouter;
        public function exposeHome(): string { return $this->routerHome(); }
    };
    expect($router->exposeHome())->toBe('/home/testuser/.fly/router');
});

it('derives routerComposePath from routerHome', function () use ($makeRouter) {
    $router = $makeRouter('/tmp/fly-test');
    expect($router->exposePath())->toBe('/tmp/fly-test/.fly/router/docker-compose.yml');
});

// ── routerDomain ──────────────────────────────────────────────────────────────

it('returns localhost as the default domain', function () use ($makeRouter) {
    putenv('FLY_ROUTER_DOMAIN=');
    $router = $makeRouter();
    expect($router->exposeDomain())->toBe('localhost');
});

it('returns the configured FLY_ROUTER_DOMAIN', function () use ($makeRouter) {
    putenv('FLY_ROUTER_DOMAIN=test');
    $router = $makeRouter();
    expect($router->exposeDomain())->toBe('test');
    putenv('FLY_ROUTER_DOMAIN=');
});

// ── sanitiseHostname ──────────────────────────────────────────────────────────

it('lowercases the hostname', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('MyApp'))->toBe('myapp');
});

it('replaces underscores with hyphens', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('my_app'))->toBe('my-app');
});

it('replaces spaces with hyphens', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('my app'))->toBe('my-app');
});

it('strips special characters', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('My App 2!'))->toBe('my-app-2');
});

it('strips leading and trailing hyphens', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('-foo-'))->toBe('foo');
});

it('truncates to 63 characters', function () use ($makeRouter) {
    $long = str_repeat('a', 70);
    expect(strlen($makeRouter()->exposeSanitise($long)))->toBe(63);
});

it('falls back to fly-app for an empty result', function () use ($makeRouter) {
    expect($makeRouter()->exposeSanitise('!!!'))->toBe('fly-app');
});

// ── routerComposeContents ─────────────────────────────────────────────────────

it('compose file references traefik v3 image', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('traefik:v3');
});

it('compose file has fixed container name', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('container_name: fly-router-traefik');
});

it('compose file binds ports 80, 443, and 8080', function () use ($makeRouter) {
    $contents = $makeRouter()->exposeContents();
    expect($contents)
        ->toContain('"80:80"')
        ->toContain('"443:443"')
        ->toContain('"8080:8080"');
});

it('compose file configures docker socket volume', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('/var/run/docker.sock:/var/run/docker.sock:ro');
});

it('compose file uses external fly-router network', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())
        ->toContain('external: true')
        ->toContain('name: fly-router');
});

it('compose file redirects HTTP to HTTPS', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('redirections.entrypoint.to=websecure');
});

it('compose file disables expose-by-default', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('exposedbydefault=false');
});

it('compose file scopes provider to fly-router network', function () use ($makeRouter) {
    expect($makeRouter()->exposeContents())->toContain('providers.docker.network=fly-router');
});
