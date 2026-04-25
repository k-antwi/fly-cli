<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class TinkerCommand extends ContainerCommand
{
    protected $signature = 'tinker';

    protected $description = 'Start a new Laravel Tinker session inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(['php', 'artisan', 'tinker']);
    }
}
