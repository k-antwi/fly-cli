<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class YarnCommand extends ContainerCommand
{
    protected $signature = 'yarn {args?*}';

    protected $description = 'Run a Yarn command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['yarn'], $this->forwardedTokens()));
    }
}
