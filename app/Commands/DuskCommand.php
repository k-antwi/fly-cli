<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class DuskCommand extends ContainerCommand
{
    protected $signature = 'dusk {args?*}';

    protected $description = 'Run the Dusk tests inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            array_merge(['php', 'artisan', 'dusk'], $this->forwardedTokens()),
            'fly',
            [
                'APP_URL' => 'http://laravel.fly',
                'DUSK_DRIVER_URL' => 'http://selenium:4444/wd/hub',
            ],
            'laravel.fly'
        );
    }
}
