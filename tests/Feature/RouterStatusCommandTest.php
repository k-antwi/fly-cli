<?php

use App\Commands\RouterStatusCommand;

it('reports network as not found when fly-router network is absent', function () {
    $cmd = new class extends RouterStatusCommand {
        protected function routerNetworkExists(): bool { return false; }
        protected function routerContainerIsRunning(): bool { return false; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:status')
        ->expectsOutputToContain('not found')
        ->assertExitCode(0);
});

it('reports container as stopped when container is not running', function () {
    $cmd = new class extends RouterStatusCommand {
        protected function routerNetworkExists(): bool { return true; }
        protected function routerContainerIsRunning(): bool { return false; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:status')
        ->expectsOutputToContain('stopped')
        ->assertExitCode(0);
});

it('suggests router:start when container is not running', function () {
    $cmd = new class extends RouterStatusCommand {
        protected function routerNetworkExists(): bool { return false; }
        protected function routerContainerIsRunning(): bool { return false; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:status')
        ->expectsOutputToContain('router:start')
        ->assertExitCode(0);
});

it('shows dashboard URL when container is running', function () {
    $cmd = new class extends RouterStatusCommand {
        protected function routerNetworkExists(): bool { return true; }
        protected function routerContainerIsRunning(): bool { return true; }
        protected function routerDomain(): string { return 'localhost'; }
        // Stub out the Traefik API call — no running container in tests
        private function printRegisteredRoutes(): void {}
    };
    $this->registerCommand($cmd);

    $this->artisan('router:status')
        ->expectsOutputToContain('http://localhost:8080')
        ->assertExitCode(0);
});

it('shows the configured domain when container is running', function () {
    $cmd = new class extends RouterStatusCommand {
        protected function routerNetworkExists(): bool { return true; }
        protected function routerContainerIsRunning(): bool { return true; }
        protected function routerDomain(): string { return 'test'; }
        private function printRegisteredRoutes(): void {}
    };
    $this->registerCommand($cmd);

    $this->artisan('router:status')
        ->expectsOutputToContain('*.test')
        ->assertExitCode(0);
});
