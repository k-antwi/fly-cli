<?php

use App\Commands\RouterStopCommand;

it('notifies the user when router is not installed', function () {
    $cmd = new class extends RouterStopCommand {
        protected function routerComposePath(): string { return '/nonexistent/docker-compose.yml'; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:stop')
        ->expectsOutputToContain('not installed')
        ->assertExitCode(0);
});

it('stops the router and shows stopped message on success', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'fly-router-compose-');
    file_put_contents($tmpFile, 'content');

    $cmd = new class($tmpFile) extends RouterStopCommand {
        public function __construct(private string $tmpFile) { parent::__construct(); }
        protected function routerComposePath(): string { return $this->tmpFile; }
        protected function stopRouter(): int { return 0; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:stop')
        ->expectsOutputToContain('stopped')
        ->assertExitCode(0);

    @unlink($tmpFile);
});

it('propagates non-zero exit code from stopRouter', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'fly-router-compose-');
    file_put_contents($tmpFile, 'content');

    $cmd = new class($tmpFile) extends RouterStopCommand {
        public function __construct(private string $tmpFile) { parent::__construct(); }
        protected function routerComposePath(): string { return $this->tmpFile; }
        protected function stopRouter(): int { return 1; }
    };
    $this->registerCommand($cmd);

    $this->artisan('router:stop')->assertExitCode(1);

    @unlink($tmpFile);
});
