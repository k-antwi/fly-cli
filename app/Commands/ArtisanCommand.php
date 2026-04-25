<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class ArtisanCommand extends ContainerCommand
{
    protected $signature = 'artisan {args?*}';

    protected $description = 'Run an Artisan command inside the application container';

    protected $aliases = ['art', 'a'];

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['php', 'artisan'], $this->forwardedTokens()));
    }
}
