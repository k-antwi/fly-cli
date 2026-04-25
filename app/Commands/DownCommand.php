<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class DownCommand extends ContainerCommand
{
    protected $signature = 'down {args?* : Arguments forwarded to "docker compose down"}';

    protected $description = 'Stop and remove the application containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['down'], $this->forwardedTokens()));
    }
}
