<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PullCommand extends ContainerCommand
{
    protected $signature = 'pull {args?*}';

    protected $description = 'Pull the latest images for the configured services';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['pull'], $this->forwardedTokens()));
    }
}
