<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PnpmCommand extends ContainerCommand
{
    protected $signature = 'pnpm {args?*}';

    protected $description = 'Run a pnpm command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['pnpm'], $this->forwardedTokens()));
    }
}
