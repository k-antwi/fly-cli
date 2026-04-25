<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class BunxCommand extends ContainerCommand
{
    protected $signature = 'bunx {args?*}';

    protected $description = 'Run a bunx command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['bunx'], $this->forwardedTokens()));
    }
}
