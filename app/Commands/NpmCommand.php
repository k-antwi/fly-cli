<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class NpmCommand extends ContainerCommand
{
    protected $signature = 'npm {args?*}';

    protected $description = 'Run an npm command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['npm'], $this->forwardedTokens()));
    }
}
