<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PintCommand extends ContainerCommand
{
    protected $signature = 'pint {args?*}';

    protected $description = 'Run Pint inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php', 'vendor/bin/pint'], $this->forwardedTokens()));
    }
}
