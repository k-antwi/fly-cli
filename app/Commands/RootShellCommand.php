<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class RootShellCommand extends ContainerCommand
{
    protected $signature = 'root-shell';

    protected $description = 'Start a root shell session inside the application container';

    protected $aliases = ['root-bash'];

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(['bash'], user: 'root');
    }
}
