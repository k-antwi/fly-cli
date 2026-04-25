<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

class AddCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'add
        {services? : The services that should be added}
        {--php=8.4 : The PHP version that should be used}';

    protected $description = 'Add a service to an existing Fly installation';

    public function handle(): int
    {
        if ($this->argument('services')) {
            $services = $this->argument('services') === 'none' ? [] : explode(',', $this->argument('services'));
        } elseif ($this->option('no-interaction')) {
            $services = $this->defaultServices;
        } else {
            $services = $this->gatherServicesInteractively();
        }

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->publishDockerResources();
        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();
        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Additional Fly services installed successfully.');

        return 0;
    }
}
