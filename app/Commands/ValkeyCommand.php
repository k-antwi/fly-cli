<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class ValkeyCommand extends ContainerCommand
{
    protected $signature = 'valkey';

    protected $description = 'Start a Valkey CLI session inside the "valkey" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(['valkey-cli'], user: '', service: 'valkey');
    }
}
