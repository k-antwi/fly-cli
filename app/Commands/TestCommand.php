<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class TestCommand extends ContainerCommand
{
    protected $signature = 'test {args?*}';

    protected $description = 'Run "php artisan test" inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php', 'artisan', 'test'], $this->forwardedTokens()));
    }
}
