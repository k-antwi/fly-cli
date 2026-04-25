<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class MongodbCommand extends ContainerCommand
{
    protected $signature = 'mongodb';

    protected $description = 'Start a MongoDB shell session inside the "mongodb" container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $port = getenv('FORWARD_MONGODB_PORT') ?: '27017';
        $user = getenv('MONGODB_USERNAME') ?: '';
        $pass = getenv('MONGODB_PASSWORD') ?: '';

        return $this->composeExec(
            [
                'mongosh', '--port', $port,
                '--username', $user,
                '--password', $pass,
                '--authenticationDatabase', 'admin',
            ],
            user: '',
            service: 'mongodb',
        );
    }
}
