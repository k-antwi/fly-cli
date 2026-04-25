<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class DuskFailsCommand extends ContainerCommand
{
    protected $signature = 'dusk:fails {args?*}';

    protected $description = 'Re-run previously failed Dusk tests';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $service = getenv('APP_SERVICE') ?: 'laravel.fly';

        return $this->composeExec(
            array_merge(['php', 'artisan', 'dusk:fails'], $this->forwardedTokens()),
            'fly',
            [
                'APP_URL' => "http://{$service}",
                'DUSK_DRIVER_URL' => 'http://selenium:4444/wd/hub',
            ]
        );
    }
}
