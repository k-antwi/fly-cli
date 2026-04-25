<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class StopCommand extends ContainerCommand
{
    protected $signature = 'stop {args?*}';

    protected $description = 'Stop the application containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['stop'], $this->forwardedTokens()));
    }
}
