<?php

namespace App\Commands\Concerns;

use App\Concerns\ProxiesDocker;
use LaravelZero\Framework\Commands\Command;

abstract class ContainerCommand extends Command
{
    use ProxiesDocker;

    protected function configure(): void
    {
        parent::configure();

        // Forward unknown options/flags transparently to the underlying tool.
        $this->ignoreValidationErrors();
    }

    protected function boot(): int
    {
        return $this->bootDockerEnv();
    }

    /**
     * Return every CLI token after the command name (positional args, flags, options).
     * Use this in proxy commands that need to forward raw flags like `--force`.
     *
     * @return array<int,string>
     */
    protected function forwardedTokens(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        // argv = [binary, command-name, ...rest]
        return array_slice($argv, 2);
    }
}
