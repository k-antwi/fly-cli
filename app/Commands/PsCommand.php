<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PsCommand extends ContainerCommand
{
    protected $signature = 'ps {args?*}';

    protected $description = 'Display the status of all containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['ps'], $this->forwardedTokens()));
    }
}
