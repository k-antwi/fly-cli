<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class NodeCommand extends ContainerCommand
{
    protected $signature = 'node {args?*}';

    protected $description = 'Run a Node command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(array_merge(['node'], $this->forwardedTokens()));
    }
}
