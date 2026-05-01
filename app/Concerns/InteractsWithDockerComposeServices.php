<?php

namespace App\Concerns;

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithDockerComposeServices
{
    /**
     * The available services that may be installed.
     *
     * @var array<string>
     */
    protected $services = [
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
        'selenium',
        'soketi',
    ];

    /**
     * The default services used when the user chooses non-interactive mode.
     *
     * @var string[]
     */
    protected $defaultServices = ['mysql', 'redis', 'selenium', 'mailpit'];

    /**
     * Path to the user's project (where they invoked `fly`).
     */
    protected function projectPath(string $path = ''): string
    {
        return rtrim(getcwd(), '/').($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Path to a bundled resource inside the binary/PHAR.
     */
    protected function resourcePath(string $path = ''): string
    {
        return base_path('resources'.($path ? '/'.ltrim($path, '/') : ''));
    }

    /**
     * Copy bundled docker runtimes & database init scripts into the user's project.
     */
    protected function publishDockerResources(): void
    {
        $this->copyDirectory($this->resourcePath('runtimes'), $this->projectPath('docker'));
        $this->copyDirectory($this->resourcePath('database'), $this->projectPath('docker'));
    }

    /**
     * Recursively copy a directory. Works through phar:// streams.
     */
    protected function copyDirectory(string $src, string $dest): void
    {
        if (! is_dir($src)) {
            return;
        }

        if (! is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
                @chmod($target, fileperms($item->getPathname()) & 0777);
            }
        }
    }

    /**
     * Gather the desired Fly services using an interactive prompt.
     */
    protected function gatherServicesInteractively(): array
    {
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $this->services,
                default: ['mysql'],
            );
        }

        return $this->choice('Which services would you like to install?', $this->services, 0, null, true);
    }

    /**
     * Build the Docker Compose file.
     */
    protected function buildDockerCompose(array $services): void
    {
        $composePath = $this->projectPath('docker-compose.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/docker-compose.stub')));

        // Prepare the installation of the "mariadb-client" package if the MariaDB service is used...
        if (in_array('mariadb', $services)) {
            $compose['services']['laravel.fly']['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
        }

        // Adds the new services as dependencies of the laravel.fly service...
        if (! array_key_exists('laravel.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the laravel.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['laravel.fly']['depends_on'] = collect($compose['services']['laravel.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        // Add the services to the docker-compose.yml...
        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        // Merge volumes...
        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);
        $this->backfillRouterLabels($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        $yaml = str_replace('{{PHP_VERSION}}', $this->hasOption('php') ? $this->option('php') : '8.4', $yaml);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Build the Docker Compose file for production.
     */
    protected function buildDockerComposeForProduction(array $services): void
    {
        $composePath = $this->projectPath('docker-compose-live.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/docker-compose.stub')));

        if (in_array('mariadb', $services)) {
            $compose['services']['laravel.fly']['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
        }

        if (! array_key_exists('laravel.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the laravel.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['laravel.fly']['depends_on'] = collect($compose['services']['laravel.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        $yaml = str_replace('{{PHP_VERSION}}', $this->hasOption('php') ? $this->option('php') : '8.4', $yaml);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     */
    protected function replaceEnvVariables(array $services): void
    {
        $envPath = $this->projectPath('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $environment = file_get_contents($envPath);

        if (in_array('mysql', $services) ||
            in_array('mariadb', $services) ||
            in_array('pgsql', $services)) {
            $defaults = [
                '# DB_HOST=127.0.0.1',
                '# DB_PORT=3306',
                '# DB_DATABASE=laravel',
                '# DB_USERNAME=root',
                '# DB_PASSWORD=',
            ];

            foreach ($defaults as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
        }

        if (in_array('mysql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mysql', $environment);
        } elseif (in_array('pgsql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=pgsql', $environment);
            $environment = str_replace('DB_PORT=3306', 'DB_PORT=5432', $environment);
        } elseif (in_array('mariadb', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mariadb', $environment);
        }

        $environment = str_replace('DB_USERNAME=root', 'DB_USERNAME=fly', $environment);
        $environment = preg_replace('/DB_PASSWORD=(.*)/', 'DB_PASSWORD=password', $environment);

        if (in_array('memcached', $services)) {
            $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        }

        if (in_array('redis', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        }

        if (in_array('valkey', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=valkey', $environment);
        }

        if (in_array('mongodb', $services)) {
            $environment .= "\nMONGODB_URI=mongodb://mongodb:27017";
            $environment .= "\nMONGODB_DATABASE=laravel";
        }

        if (in_array('meilisearch', $services)) {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
            $environment .= "\nMEILISEARCH_NO_ANALYTICS=false\n";
        }

        if (in_array('typesense', $services)) {
            $environment .= "\nSCOUT_DRIVER=typesense";
            $environment .= "\nTYPESENSE_HOST=typesense";
            $environment .= "\nTYPESENSE_PORT=8108";
            $environment .= "\nTYPESENSE_PROTOCOL=http";
            $environment .= "\nTYPESENSE_API_KEY=xyz\n";
        }

        if (in_array('soketi', $services)) {
            $environment = preg_replace('/^BROADCAST_DRIVER=(.*)/m', 'BROADCAST_DRIVER=pusher', $environment);
            $environment = preg_replace('/^PUSHER_APP_ID=(.*)/m', 'PUSHER_APP_ID=app-id', $environment);
            $environment = preg_replace('/^PUSHER_APP_KEY=(.*)/m', 'PUSHER_APP_KEY=app-key', $environment);
            $environment = preg_replace('/^PUSHER_APP_SECRET=(.*)/m', 'PUSHER_APP_SECRET=app-secret', $environment);
            $environment = preg_replace('/^PUSHER_HOST=(.*)/m', 'PUSHER_HOST=soketi', $environment);
            $environment = preg_replace('/^PUSHER_PORT=(.*)/m', 'PUSHER_PORT=6001', $environment);
            $environment = preg_replace('/^PUSHER_SCHEME=(.*)/m', 'PUSHER_SCHEME=http', $environment);
            $environment = preg_replace('/^VITE_PUSHER_HOST=(.*)/m', 'VITE_PUSHER_HOST=localhost', $environment);
        }

        if (in_array('mailpit', $services)) {
            $environment = preg_replace('/^MAIL_MAILER=(.*)/m', 'MAIL_MAILER=smtp', $environment);
            $environment = preg_replace('/^MAIL_HOST=(.*)/m', 'MAIL_HOST=mailpit', $environment);
            $environment = preg_replace('/^MAIL_PORT=(.*)/m', 'MAIL_PORT=1025', $environment);
        }

        file_put_contents($envPath, $environment);
    }

    protected function prepareEnvForProduction(array $services): void
    {
        // Same logic as replaceEnvVariables for now; kept separate so production
        // tweaks can diverge without touching the local flow.
        $this->replaceEnvVariables($services);
    }

    /**
     * Configure PHPUnit to use the dedicated testing database.
     */
    protected function configurePhpUnit(): void
    {
        if (! file_exists($path = $this->projectPath('phpunit.xml'))) {
            $path = $this->projectPath('phpunit.xml.dist');

            if (! file_exists($path)) {
                return;
            }
        }

        $phpunit = file_get_contents($path);

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace('<!-- <env name="DB_DATABASE" value=":memory:"/> -->', '<env name="DB_DATABASE" value="testing"/>', $phpunit);

        file_put_contents($this->projectPath('phpunit.xml'), $phpunit);
    }

    /**
     * Install the devcontainer.json configuration file.
     */
    protected function installDevContainer(): void
    {
        if (! is_dir($this->projectPath('.devcontainer'))) {
            mkdir($this->projectPath('.devcontainer'), 0755, true);
        }

        file_put_contents(
            $this->projectPath('.devcontainer/devcontainer.json'),
            file_get_contents($this->resourcePath('stubs/devcontainer.stub'))
        );

        if (file_exists($envPath = $this->projectPath('.env'))) {
            $environment = file_get_contents($envPath);
            $environment .= "\nWWWGROUP=1000";
            $environment .= "\nWWWUSER=1000\n";
            file_put_contents($envPath, $environment);
        }
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     */
    protected function prepareInstallation(array $services): void
    {
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return;
        }

        $bin = $this->flyBinary();

        if (count($services) > 0) {
            $this->runCommands([
                "{$bin} pull ".implode(' ', $services),
            ]);
        }

        $this->runCommands(["{$bin} build"]);
    }

    /**
     * Resolve the path the user should invoke for follow-up fly commands.
     */
    protected function flyBinary(): string
    {
        $argv = $_SERVER['argv'][0] ?? 'fly';

        return str_starts_with($argv, '/') ? $argv : 'fly';
    }

    protected function addRouterNetworkIfAbsent(array &$compose): void
    {
        if (! isset($compose['networks']['fly-router'])) {
            $compose['networks']['fly-router'] = ['external' => true, 'name' => 'fly-router'];
        }
    }

    protected function backfillRouterLabels(array &$compose): void
    {
        if (isset($compose['services']['laravel.fly']) &&
            empty($compose['services']['laravel.fly']['labels'])) {
            $compose['services']['laravel.fly']['labels'] = [
                'traefik.enable=true',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.rule=Host(`${FLY_APP_HOST:-fly-app.localhost}`)',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.entrypoints=websecure',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.tls=true',
                'traefik.http.services.${APP_NAME:-fly-app}-app.loadbalancer.server.port=80',
            ];
        }

        if (isset($compose['services']['laravel.fly']['networks']) &&
            ! in_array('fly-router', (array) $compose['services']['laravel.fly']['networks'])) {
            $compose['services']['laravel.fly']['networks'][] = 'fly-router';
        }
    }

    /**
     * Run the given commands.
     */
    protected function runCommands(array $commands): int
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), $this->projectPath(), null, null, null);

        if (DIRECTORY_SEPARATOR !== '\\' && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });
    }

    /**
     * Copy bundled Node.js runtime into the user's project.
     */
    protected function publishNodeDockerResources(): void
    {
        $this->copyDirectory($this->resourcePath('runtimes/node'), $this->projectPath('docker/node'));
        $this->copyDirectory($this->resourcePath('database'), $this->projectPath('docker'));
    }

    /**
     * Build the Docker Compose file for Node.js projects.
     */
    protected function buildNodeDockerCompose(array $services): void
    {
        $composePath = $this->projectPath('docker-compose.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/node-docker-compose.stub')));

        if (! array_key_exists('node.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the node.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['node.fly']['depends_on'] = collect($compose['services']['node.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);
        $this->backfillNodeRouterLabels($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Build the Docker Compose file for Node.js projects (production).
     */
    protected function buildNodeDockerComposeForProduction(array $services): void
    {
        $composePath = $this->projectPath('docker-compose-live.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/node-docker-compose.stub')));

        if (! array_key_exists('node.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the node.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['node.fly']['depends_on'] = collect($compose['services']['node.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Add Traefik router labels to the node.fly service.
     */
    protected function backfillNodeRouterLabels(array &$compose): void
    {
        if (isset($compose['services']['node.fly']) &&
            empty($compose['services']['node.fly']['labels'])) {
            $compose['services']['node.fly']['labels'] = [
                'traefik.enable=true',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.rule=Host(`${FLY_APP_HOST:-fly-app.localhost}`)',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.entrypoints=websecure',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.tls=true',
                'traefik.http.services.${APP_NAME:-fly-app}-app.loadbalancer.server.port=3000',
            ];
        }

        if (isset($compose['services']['node.fly']['networks']) &&
            ! in_array('fly-router', (array) $compose['services']['node.fly']['networks'])) {
            $compose['services']['node.fly']['networks'][] = 'fly-router';
        }
    }

    /**
     * Copy bundled Vue.js runtime into the user's project.
     */
    protected function publishVueDockerResources(): void
    {
        $this->copyDirectory($this->resourcePath('runtimes/vue'), $this->projectPath('docker/vue'));
        $this->copyDirectory($this->resourcePath('database'), $this->projectPath('docker'));
    }

    /**
     * Build the Docker Compose file for Vue.js projects.
     */
    protected function buildVueDockerCompose(array $services): void
    {
        $composePath = $this->projectPath('docker-compose.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/vue-docker-compose.stub')));

        if (! array_key_exists('vue.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the vue.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['vue.fly']['depends_on'] = collect($compose['services']['vue.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);
        $this->backfillVueRouterLabels($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Build the Docker Compose file for Vue.js projects (production).
     */
    protected function buildVueDockerComposeForProduction(array $services): void
    {
        $composePath = $this->projectPath('docker-compose-live.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/vue-docker-compose.stub')));

        if (! array_key_exists('vue.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the vue.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['vue.fly']['depends_on'] = collect($compose['services']['vue.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Add Traefik router labels to the vue.fly service.
     */
    protected function backfillVueRouterLabels(array &$compose): void
    {
        if (isset($compose['services']['vue.fly']) &&
            empty($compose['services']['vue.fly']['labels'])) {
            $compose['services']['vue.fly']['labels'] = [
                'traefik.enable=true',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.rule=Host(`${FLY_APP_HOST:-fly-app.localhost}`)',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.entrypoints=websecure',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.tls=true',
                'traefik.http.services.${APP_NAME:-fly-app}-app.loadbalancer.server.port=5173',
            ];
        }

        if (isset($compose['services']['vue.fly']['networks']) &&
            ! in_array('fly-router', (array) $compose['services']['vue.fly']['networks'])) {
            $compose['services']['vue.fly']['networks'][] = 'fly-router';
        }
    }

    /**
     * Copy bundled React runtime into the user's project.
     */
    protected function publishReactDockerResources(): void
    {
        $this->copyDirectory($this->resourcePath('runtimes/react'), $this->projectPath('docker/react'));
        $this->copyDirectory($this->resourcePath('database'), $this->projectPath('docker'));
    }

    /**
     * Build the Docker Compose file for React projects.
     */
    protected function buildReactDockerCompose(array $services): void
    {
        $composePath = $this->projectPath('docker-compose.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/react-docker-compose.stub')));

        if (! array_key_exists('react.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the react.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['react.fly']['depends_on'] = collect($compose['services']['react.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);
        $this->backfillReactRouterLabels($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Build the Docker Compose file for React projects (production).
     */
    protected function buildReactDockerComposeForProduction(array $services): void
    {
        $composePath = $this->projectPath('docker-compose-live.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents($this->resourcePath('stubs/react-docker-compose.stub')));

        if (! array_key_exists('react.fly', $compose['services'])) {
            $this->warn('Couldn\'t find the react.fly service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['react.fly']['depends_on'] = collect($compose['services']['react.fly']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile($this->resourcePath("stubs/{$service}.stub"))[$service];
            });

        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["fly-{$service}"] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $this->addRouterNetworkIfAbsent($compose);

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Add Traefik router labels to the react.fly service.
     */
    protected function backfillReactRouterLabels(array &$compose): void
    {
        if (isset($compose['services']['react.fly']) &&
            empty($compose['services']['react.fly']['labels'])) {
            $compose['services']['react.fly']['labels'] = [
                'traefik.enable=true',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.rule=Host(`${FLY_APP_HOST:-fly-app.localhost}`)',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.entrypoints=websecure',
                'traefik.http.routers.${APP_NAME:-fly-app}-app.tls=true',
                'traefik.http.services.${APP_NAME:-fly-app}-app.loadbalancer.server.port=5173',
            ];
        }

        if (isset($compose['services']['react.fly']['networks']) &&
            ! in_array('fly-router', (array) $compose['services']['react.fly']['networks'])) {
            $compose['services']['react.fly']['networks'][] = 'fly-router';
        }
    }
}
