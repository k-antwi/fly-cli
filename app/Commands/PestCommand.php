<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PestCommand extends ContainerCommand
{
    protected $signature = 'pest {args?*}';

    protected $description = 'Run Pest inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php', 'vendor/bin/pest'], $this->forwardedTokens()));
    }
}
