<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class MysqlCommand extends ContainerCommand
{
    protected $signature = 'mysql';

    protected $description = 'Start a MySQL CLI session inside the "mysql" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        return $this->composeExec(
            ['bash', '-c', 'MYSQL_PWD=${MYSQL_PASSWORD} mysql -u ${MYSQL_USER} ${MYSQL_DATABASE}'],
            user: '',
            service: 'mysql',
        );
    }
}
