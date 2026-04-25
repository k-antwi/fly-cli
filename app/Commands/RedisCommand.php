<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class RedisCommand extends ContainerCommand
{
    protected $signature = 'redis';

    protected $description = 'Start a Redis CLI session inside the "redis" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(['redis-cli'], user: '', service: 'redis');
    }
}
