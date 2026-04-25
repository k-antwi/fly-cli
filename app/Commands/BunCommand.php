<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class BunCommand extends ContainerCommand
{
    protected $signature = 'bun {args?*}';

    protected $description = 'Run a Bun command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['bun'], $this->forwardedTokens()));
    }
}
