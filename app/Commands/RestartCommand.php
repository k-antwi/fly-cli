<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class RestartCommand extends ContainerCommand
{
    protected $signature = 'restart {args?*}';

    protected $description = 'Restart the application containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['restart'], $this->forwardedTokens()));
    }
}
