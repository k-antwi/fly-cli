<?php

use App\Commands\UpCommand;

beforeEach(fn () => chdir(sys_get_temp_dir()));

it('bootstraps the router on first run when network does not exist', function () {
    $startCalled = false;

    $cmd = new class($startCalled) extends UpCommand {
        public function __construct(public bool &$startCalled) { parent::__construct(); }
        protected function boot(): int { return 0; }
        protected function routerNetworkExists(): bool { return false; }
        protected function startRouter(): int { $this->startCalled = true; return 0; }
        protected function dockerComposePassthrough(array $args): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('up')->assertExitCode(0);
    expect($startCalled)->toBeTrue();
});

it('restarts the router when network exists but container is stopped', function () {
    $startCalled = false;

    $cmd = new class($startCalled) extends UpCommand {
        public function __construct(public bool &$startCalled) { parent::__construct(); }
        protected function boot(): int { return 0; }
        protected function routerNetworkExists(): bool { return true; }
        protected function routerContainerIsRunning(): bool { return false; }
        protected function startRouter(): int { $this->startCalled = true; return 0; }
        protected function dockerComposePassthrough(array $args): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('up')->assertExitCode(0);
    expect($startCalled)->toBeTrue();
});

it('skips startRouter when router is already running', function () {
    $startCalled = false;

    $cmd = new class($startCalled) extends UpCommand {
        public function __construct(public bool &$startCalled) { parent::__construct(); }
        protected function boot(): int { return 0; }
        protected function routerNetworkExists(): bool { return true; }
        protected function routerContainerIsRunning(): bool { return true; }
        protected function startRouter(): int { $this->startCalled = true; return 0; }
        protected function dockerComposePassthrough(array $args): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('up')->assertExitCode(0);
    expect($startCalled)->toBeFalse();
});

it('prints an initialising message on first bootstrap', function () {
    $cmd = new class extends UpCommand {
        protected function boot(): int { return 0; }
        protected function routerNetworkExists(): bool { return false; }
        protected function startRouter(): int { return 0; }
        protected function dockerComposePassthrough(array $args): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('up')
        ->expectsOutputToContain('fly router')
        ->assertExitCode(0);
});

it('propagates a non-zero boot exit code without touching the router', function () {
    $startCalled = false;

    $cmd = new class($startCalled) extends UpCommand {
        public function __construct(public bool &$startCalled) { parent::__construct(); }
        protected function boot(): int { return 1; }
        protected function routerNetworkExists(): bool { $this->startCalled = true; return false; }
        protected function startRouter(): int { $this->startCalled = true; return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('up')->assertExitCode(1);
    expect($startCalled)->toBeFalse();
});
