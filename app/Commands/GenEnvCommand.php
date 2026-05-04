<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;

class GenEnvCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'gen:env
        {--force : Overwrite the .env.fly file if it already exists}';

    protected $description = 'Generate a .fly/.env.fly reference file with all available Fly environment variables';

    public function handle(): int
    {
        $dir = $this->projectPath('.fly');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $target = $dir.'/.env.fly';

        if (file_exists($target) && ! $this->option('force')) {
            if (! confirm(label: '.fly/.env.fly already exists. Overwrite?', default: false)) {
                $this->components->warn('Aborted; existing file left unchanged.');

                return 1;
            }
        }

        $stub = file_get_contents($this->resourcePath('stubs/env-fly.stub'));
        file_put_contents($target, $stub);

        $this->output->writeln('');
        $this->components->info('Fly environment reference generated at .fly/.env.fly');

        return 0;
    }
}
