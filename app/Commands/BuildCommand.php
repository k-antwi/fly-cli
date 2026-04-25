<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class BuildCommand extends ContainerCommand
{
    protected $signature = 'build {args?*}';

    protected $description = 'Build or rebuild the Fly containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->dockerComposePassthrough(array_merge(['build'], $this->forwardedTokens()));
    }
}
