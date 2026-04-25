<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class ShellCommand extends ContainerCommand
{
    protected $signature = 'shell';

    protected $description = 'Start a shell session inside the application container';

    protected $aliases = ['bash'];

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(['bash']);
    }
}
