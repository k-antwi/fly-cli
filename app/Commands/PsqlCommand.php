<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class PsqlCommand extends ContainerCommand
{
    protected $signature = 'psql';

    protected $description = 'Start a PostgreSQL CLI session inside the "pgsql" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            ['bash', '-c', 'PGPASSWORD=${PGPASSWORD} psql -U ${POSTGRES_USER} ${POSTGRES_DB}'],
            user: '',
            service: 'pgsql',
        );
    }
}
