<?php

namespace App\Concerns;

use Symfony\Component\Process\Process;

trait ProxiesDocker
{
    /** @var array<int,string> The detected base docker compose command. */
    protected array $dockerCompose = [];

    /** @var bool Whether containers are currently running for this project. */
    protected bool $isRunning = false;

    /**
     * Path to the project the user is operating on (cwd at invocation time).
     */
    protected function projectPath(string $path = ''): string
    {
        return rtrim(getcwd(), '/').($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Bootstrap the docker compose environment. Loads .env, sets defaults,
     * exports random ports, picks the docker compose binary, and (unless
     * FLY_SKIP_CHECKS is set) verifies docker is reachable and inspects
     * the running container state.
     *
     * Returns 0 on success or a non-zero status to abort the command.
     */
    protected function bootDockerEnv(): int
    {
        $this->loadDotEnv();

        $this->setEnvDefault('APP_NAME', 'fly-app');
        $this->setEnvDefault('APP_SERVICE', $this->detectAppService());
        $this->setEnvDefault('FLY_ROUTER_DOMAIN', 'localhost');

        $appName = getenv('APP_NAME') ?: 'fly-app';
        $domain  = getenv('FLY_ROUTER_DOMAIN') ?: 'localhost';
        $host    = $this->sanitiseAppHostname($appName).'.'.$domain;
        putenv('FLY_APP_HOST='.$host);
        $_ENV['FLY_APP_HOST'] = $host;
        $this->setEnvDefault('WWWUSER', (string) (function_exists('posix_geteuid') ? posix_geteuid() : 1000));
        $this->setEnvDefault('WWWGROUP', (string) (function_exists('posix_getegid') ? posix_getegid() : 1000));

        $this->setEnvDefault('FLY_FILES', '');
        $this->setEnvDefault('FLY_SHARE_DASHBOARD', '4040');
        $this->setEnvDefault('FLY_SHARE_SERVER_HOST', 'laravel-fly.site');
        $this->setEnvDefault('FLY_SHARE_SERVER_PORT', '8080');
        $this->setEnvDefault('FLY_SHARE_SUBDOMAIN', '');
        $this->setEnvDefault('FLY_SHARE_DOMAIN', getenv('FLY_SHARE_SERVER_HOST') ?: 'laravel-fly.site');
        $this->setEnvDefault('FLY_SHARE_SERVER', '');

        foreach ([
            'APP_PORT', 'FORWARD_DB_PORT', 'FORWARD_REDIS_PORT', 'FORWARD_VALKEY_PORT',
            'FORWARD_MONGODB_PORT', 'FORWARD_COUCHDB_PORT', 'FORWARD_PGSQL_PORT', 'VITE_PORT', 'PUSHER_PORT',
            'PUSHER_METRICS_PORT', 'FORWARD_TYPESENSE_PORT', 'ODOO_APP_PORT', 'ODOO_LONG_POLLING',
            'FORWARD_MINIO_PORT', 'FORWARD_MINIO_CONSOLE_PORT', 'FORWARD_MEMCACHED_PORT',
            'FORWARD_MEILISEARCH_PORT',
        ] as $portVar) {
            putenv($portVar.'='.$this->randomPort());
            $_ENV[$portVar] = getenv($portVar);
        }

        $this->dockerCompose = $this->resolveDockerCompose();

        if (! $this->dockerCompose) {
            $this->output->writeln('<error>Neither "docker compose" nor "docker-compose" is available.</error>');

            return 1;
        }

        if (($files = getenv('FLY_FILES')) !== false && $files !== '') {
            foreach (explode(':', $files) as $file) {
                if (! file_exists($this->projectPath($file)) && ! file_exists($file)) {
                    $this->output->writeln("<error>Unable to find Docker Compose file: '{$file}'</error>");

                    return 1;
                }
                $this->dockerCompose[] = '-f';
                $this->dockerCompose[] = $file;
            }
        }

        if (getenv('FLY_SKIP_CHECKS') === false || getenv('FLY_SKIP_CHECKS') === '') {
            if (! $this->dockerIsRunning()) {
                $this->output->writeln('<error>Docker is not running.</error>');

                return 1;
            }

            $this->isRunning = $this->detectRunningState();
        } else {
            $this->isRunning = true;
        }

        return 0;
    }

    /**
     * Run `docker compose <args>` and stream output. Used by up/down/stop/etc.
     */
    protected function dockerComposePassthrough(array $args): int
    {
        return $this->runProcess(array_merge($this->dockerCompose, $args));
    }

    /**
     * Run a command inside the application container via `docker compose exec`.
     *
     * @param  array<int,string>  $command  The command + args to run.
     * @param  string  $user  The container user (fly, root, or '' for default).
     * @param  array<string,string>  $env  Extra env vars to pass via -e.
     * @param  string|null  $service  The service to exec against (default APP_SERVICE).
     */
    protected function composeExec(array $command, string $user = 'fly', array $env = [], ?string $service = null): int
    {
        if (! $this->isRunning) {
            $this->flyIsNotRunning();

            return 1;
        }

        $argv = array_merge($this->dockerCompose, ['exec']);

        if ($user !== '') {
            $argv[] = '-u';
            $argv[] = $user;
        }

        foreach ($env as $key => $value) {
            $argv[] = '-e';
            $argv[] = "{$key}={$value}";
        }

        if (! $this->stdinIsTty()) {
            $argv[] = '-T';
        }

        $argv[] = $service ?? (getenv('APP_SERVICE') ?: $this->detectAppService());

        return $this->runProcess(array_merge($argv, $command));
    }

    /**
     * Run a raw process and stream output, with TTY when available.
     *
     * @param  array<int,string>  $argv
     */
    protected function runProcess(array $argv, ?string $cwd = null): int
    {
        $process = new Process($argv, $cwd ?? $this->projectPath(), $this->envForChild(), null, null);

        if (DIRECTORY_SEPARATOR !== '\\' && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException) {
                // fall through to non-TTY mode
            }
        }

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }

    /**
     * Run a shell command (`sh -c "..."`) and stream output.
     */
    protected function runShell(string $command, ?string $cwd = null): int
    {
        $process = Process::fromShellCommandline($command, $cwd ?? $this->projectPath(), $this->envForChild(), null, null);

        if (DIRECTORY_SEPARATOR !== '\\' && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException) {
                // ignore
            }
        }

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }

    protected function flyIsNotRunning(): void
    {
        $this->output->writeln('<options=bold>Fly is not running.</>');
        $this->output->writeln('');
        $this->output->writeln('You may start Fly using: <options=bold>fly up</> or <options=bold>fly up -d</>');
    }

    /**
     * Resolve the `docker compose` (or `docker-compose`) base command.
     *
     * @return array<int,string>|null
     */
    protected function resolveDockerCompose(): ?array
    {
        $check = new Process(['docker', 'compose', 'version']);
        $check->run();

        if ($check->isSuccessful()) {
            return ['docker', 'compose'];
        }

        $legacy = new Process(['docker-compose', '--version']);
        $legacy->run();

        return $legacy->isSuccessful() ? ['docker-compose'] : null;
    }

    protected function dockerIsRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->run();

        return $process->isSuccessful();
    }

    protected function detectRunningState(): bool
    {
        $service = getenv('APP_SERVICE') ?: $this->detectAppService();

        // Probe for an exited service container; if found, take the stack down.
        $argv = array_merge($this->dockerCompose, ['ps', $service]);
        $ps = new Process($argv, $this->projectPath(), $this->envForChild());
        $ps->run();

        $output = $ps->getOutput().$ps->getErrorOutput();

        if (preg_match('/Exit|exited/i', $output)) {
            $down = new Process(array_merge($this->dockerCompose, ['down']), $this->projectPath(), $this->envForChild());
            $down->run();

            return false;
        }

        $idArgv = array_merge($this->dockerCompose, ['ps', '-q']);
        $ids = new Process($idArgv, $this->projectPath(), $this->envForChild());
        $ids->run();

        return trim($ids->getOutput()) !== '';
    }

    protected function detectAppService(): string
    {
        $composePath = $this->projectPath('docker-compose.yml');

        if (! file_exists($composePath)) {
            return 'laravel.fly';
        }

        $content = file_get_contents($composePath);

        // Find the first service name ending in .fly — that's the app container.
        // Infrastructure services (mysql, redis, etc.) never use this suffix.
        if (preg_match('/^\s+([a-z][a-z0-9-]*\.fly)\s*:/m', $content, $matches)) {
            return $matches[1];
        }

        return 'laravel.fly';
    }

    protected function loadDotEnv(): void
    {
        $appEnv = getenv('APP_ENV');
        $candidate = ($appEnv !== false && $appEnv !== '') ? $this->projectPath('.env.'.$appEnv) : null;

        $envFile = ($candidate && file_exists($candidate)) ? $candidate : $this->projectPath('.env');

        if (! file_exists($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    protected function setEnvDefault(string $key, string $default): void
    {
        $existing = getenv($key);
        if ($existing === false || $existing === '') {
            putenv("{$key}={$default}");
            $_ENV[$key] = $default;
        }
    }

    /**
     * Pick a free ephemeral port (49152..65535).
     */
    protected function randomPort(): int
    {
        for ($i = 0; $i < 50; $i++) {
            $port = 49152 + random_int(0, 16383);
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.05);
            if ($sock === false) {
                return $port;
            }
            fclose($sock);
        }

        return 49152 + random_int(0, 16383);
    }

    /**
     * Build the env array passed to child processes (current env + our overrides).
     *
     * @return array<string,string>
     */
    protected function envForChild(): array
    {
        $env = [];
        foreach ($_ENV as $k => $v) {
            $env[$k] = (string) $v;
        }
        // Make sure putenv()-only values propagate too.
        foreach ([
            'APP_NAME', 'APP_SERVICE', 'WWWUSER', 'WWWGROUP', 'APP_PORT',
            'FORWARD_DB_PORT', 'FORWARD_REDIS_PORT', 'FORWARD_VALKEY_PORT',
            'FORWARD_MONGODB_PORT', 'FORWARD_COUCHDB_PORT', 'FORWARD_PGSQL_PORT', 'VITE_PORT',
            'PUSHER_PORT', 'PUSHER_METRICS_PORT', 'FORWARD_TYPESENSE_PORT',
            'ODOO_APP_PORT', 'ODOO_LONG_POLLING', 'FORWARD_MINIO_PORT',
            'FORWARD_MINIO_CONSOLE_PORT', 'FORWARD_MEMCACHED_PORT',
            'FORWARD_MEILISEARCH_PORT', 'FLY_FILES', 'FLY_SHARE_DASHBOARD',
            'FLY_SHARE_SERVER_HOST', 'FLY_SHARE_SERVER_PORT', 'FLY_SHARE_SUBDOMAIN',
            'FLY_SHARE_DOMAIN', 'FLY_SHARE_SERVER', 'APP_URL',
            'FLY_APP_HOST', 'FLY_ROUTER_DOMAIN',
        ] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        return $env;
    }

    protected function stdinIsTty(): bool
    {
        return defined('STDIN') && function_exists('posix_isatty') && @posix_isatty(STDIN);
    }

    private function sanitiseAppHostname(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[_\s]+/', '-', $name);
        $name = preg_replace('/[^a-z0-9-]/', '', $name);
        $name = trim($name, '-');

        return substr($name, 0, 63) ?: 'fly-app';
    }
}
