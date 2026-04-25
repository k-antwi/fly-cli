<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class LogsCommand extends ContainerCommand
{
    protected $signature = 'logs {args?*}';

    protected $description = 'Tail container logs';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['logs'], $this->forwardedTokens()));
    }
}
