<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

class InstallNodeCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'install:node
                {--with= : The services that should be included in the installation}
                {--node=22 : The Node.js version that should be used}
                {--live : Install the services on the remote server}';

    protected $description = "Install Fly's Docker Compose file for Node.js projects";

    protected $nodeDefaultServices = ['mysql', 'redis', 'mailpit'];

    protected $nodeServices = [
        'mysql',
        'pgsql',
        'mariadb',
        'mongodb',
        'redis',
        'valkey',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'mailpit',
        'soketi',
    ];

    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->installRemotely();
        }

        $services = $this->resolveServices();

        if ($invalidServices = array_diff($services, $this->nodeServices)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->publishNodeDockerResources();
        $this->buildNodeDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Fly scaffolding installed successfully. You may run your Docker containers using Fly\'s "up" command.');

        $this->output->writeln('<fg=gray>➜</> <options=bold>fly up</>');

        if (in_array('mysql', $services) || in_array('mariadb', $services) || in_array('pgsql', $services)) {
            $this->components->warn('A database service was installed. Remember to set up your database:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>npm run migrate</> (or your DB setup script)');
        }

        $this->output->writeln('');

        return 0;
    }

    private function installRemotely(): int
    {
        $services = $this->resolveServices();

        if ($invalidServices = array_diff($services, $this->nodeServices)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->publishNodeDockerResources();
        $this->buildNodeDockerComposeForProduction($services);
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
            return $this->nodeDefaultServices;
        }

        return $this->gatherNodeServicesInteractively();
    }

    private function gatherNodeServicesInteractively(): array
    {
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $this->nodeServices,
                default: ['mysql'],
            );
        }

        return $this->choice('Which services would you like to install?', $this->nodeServices, 0, null, true);
    }
}
