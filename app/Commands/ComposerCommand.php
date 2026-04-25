<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class ComposerCommand extends ContainerCommand
{
    protected $signature = 'composer {args?*}';

    protected $description = 'Run a Composer command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['composer'], $this->forwardedTokens()));
    }
}
