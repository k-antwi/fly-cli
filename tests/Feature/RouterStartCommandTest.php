<?php

use App\Commands\RouterStartCommand;

it('exits with error when Docker is not running', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return false; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')
        ->expectsOutput('Docker is not running.')
        ->assertExitCode(1);
});

it('starts the router successfully', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return true; }
        protected function startRouter(): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')->assertExitCode(0);
});

it('shows the Traefik dashboard URL on success', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return true; }
        protected function startRouter(): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')
        ->expectsOutputToContain('http://localhost:8080')
        ->assertExitCode(0);
});

it('shows localhost domain tip when domain is localhost', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return true; }
        protected function startRouter(): int { return 0; }
        protected function routerDomain(): string { return 'localhost'; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')
        ->expectsOutputToContain('No DNS configuration required')
        ->assertExitCode(0);
});

it('shows dnsmasq tip when domain is test', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return true; }
        protected function startRouter(): int { return 0; }
        protected function routerDomain(): string { return 'test'; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')
        ->expectsOutputToContain('dnsmasq')
        ->assertExitCode(0);
});

it('deletes the compose file when --force is passed', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'fly-router-').'.yml';
    file_put_contents($tmpFile, 'existing content');

    $cmd = new class($tmpFile) extends RouterStartCommand {
        public function __construct(private string $tmpFile) { parent::__construct(); }
        protected function dockerIsRunning(): bool { return true; }
        protected function routerComposePath(): string { return $this->tmpFile; }
        protected function startRouter(): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start', ['--force' => true])->assertExitCode(0);

    expect(file_exists($tmpFile))->toBeFalse();
});

it('returns non-zero exit code when startRouter fails', function () {
    $cmd = new class extends RouterStartCommand {
        protected function dockerIsRunning(): bool { return true; }
        protected function startRouter(): int { return 1; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:start')->assertExitCode(1);
});
