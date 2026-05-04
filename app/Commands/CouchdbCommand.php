<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class CouchdbCommand extends ContainerCommand
{
    protected $signature = 'couchdb';

    protected $description = 'Start a shell session inside the "couchdb" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            ['bash'],
            user: '',
            service: 'couchdb',
        );
    }
}
