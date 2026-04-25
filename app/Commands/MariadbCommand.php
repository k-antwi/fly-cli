<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class MariadbCommand extends ContainerCommand
{
    protected $signature = 'mariadb';

    protected $description = 'Start a MariaDB CLI session inside the "mariadb" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            ['bash', '-c', 'MYSQL_PWD=${MYSQL_PASSWORD} mariadb -u ${MYSQL_USER} ${MYSQL_DATABASE}'],
            user: '',
            service: 'mariadb',
        );
    }
}
