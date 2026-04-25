<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class NpxCommand extends ContainerCommand
{
    protected $signature = 'npx {args?*}';

    protected $description = 'Run an npx command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['npx'], $this->forwardedTokens()));
    }
}
