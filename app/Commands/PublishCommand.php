<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

class PublishCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'publish';

    protected $description = 'Publish the bundled Fly Docker files to ./docker/';

    public function handle(): int
    {
        $this->publishDockerResources();

        $this->components->info('Fly Docker files published to ./docker/');

        return 0;
    }
}
