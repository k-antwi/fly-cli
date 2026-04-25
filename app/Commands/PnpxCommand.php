<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PnpxCommand extends ContainerCommand
{
    protected $signature = 'pnpx {args?*}';

    protected $description = 'Run a pnpx command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['pnpx'], $this->forwardedTokens()));
    }
}
