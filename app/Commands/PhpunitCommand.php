<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PhpunitCommand extends ContainerCommand
{
    protected $signature = 'phpunit {args?*}';

    protected $description = 'Run PHPUnit inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php', 'vendor/bin/phpunit'], $this->forwardedTokens()));
    }
}
