<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

class InstallCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'install
                {--with= : The services that should be included in the installation}
                {--devcontainer : Create a .devcontainer configuration directory}
                {--php=8.4 : The PHP version that should be used}
                {--live : Install the services on the remote server}
                {--no-mutagen : Use Docker bind mounts instead of Mutagen file sync}';

    protected $description = "Install Fly's default Docker Compose file";

    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->installRemotely();
        }

        $services = $this->resolveServices();

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->publishDockerResources();
        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();

        if ($this->option('devcontainer')) {
            $this->installDevContainer();
        }

        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Fly scaffolding installed successfully. You may run your Docker containers using Fly\'s "up" command.');

        $this->output->writeln('<fg=gray>➜</> <options=bold>fly up</>');

        if (in_array('mysql', $services) || in_array('mariadb', $services) || in_array('pgsql', $services)) {
            $this->components->warn('A database service was installed. Run "artisan migrate" to prepare your database:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>fly artisan migrate</>');
        }

        $this->output->writeln('');

        return 0;
    }

    private function installRemotely(): int
    {
        $services = $this->resolveServices();

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->publishDockerResources();
        $this->buildDockerComposeForProduction($services);
        $this->prepareEnvForProduction($services);
        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Fly production scaffolding installed successfully.');
        $this->output->writeln('');

        return 0;
    }

    private function resolveServices(): array
    {
        if ($this->option('with')) {
            return $this->option('with') === 'none' ? [] : explode(',', $this->option('with'));
        }

        if ($this->option('no-interaction')) {
            return $this->defaultServices;
        }

        return $this->gatherServicesInteractively();
    }
}
