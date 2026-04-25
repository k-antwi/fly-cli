<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class UpCommand extends ContainerCommand
{
    protected $signature = 'up {args?* : Arguments forwarded to "docker compose up"}';

    protected $description = 'Start the application containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['up'], $this->forwardedTokens()));
    }
}
