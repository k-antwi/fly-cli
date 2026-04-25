<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PhpCommand extends ContainerCommand
{
    protected $signature = 'php {args?*}';

    protected $description = 'Run a PHP command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php'], $this->forwardedTokens()));
    }
}
