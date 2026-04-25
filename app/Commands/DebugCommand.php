<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class DebugCommand extends ContainerCommand
{
    protected $signature = 'debug {args?*}';

    protected $description = 'Run an Artisan command with Xdebug enabled';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            array_merge(['php', 'artisan'], $this->forwardedTokens()),
            'fly',
            ['XDEBUG_TRIGGER' => '1']
        );
    }
}
