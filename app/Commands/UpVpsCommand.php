<?php

namespace App\Commands;

use App\Concerns\ProxiesDocker;
use LaravelZero\Framework\Commands\Command;

class UpVpsCommand extends Command
{
    use ProxiesDocker;

    protected $signature = 'up:vps {args?* : Arguments forwarded to "docker compose" on the remote host}';

    protected $description = 'Run docker compose against a remote VPS over SSH';

    public function handle(): int
    {
        $this->loadDotEnv();

        $sshUser = getenv('FLY_SSH_USERNAME');
        if (! $sshUser) {
            $this->output->writeln('<error>SSH username is required. Please set the FLY_SSH_USERNAME environment variable.</error>');

            return 1;
        }

        $remoteAppDir = getenv('FLY_REMOTE_APP_DIR');
        if (! $remoteAppDir) {
            $this->output->writeln('<error>Remote app directory is required. Please set the FLY_REMOTE_APP_DIR environment variable.</error>');

            return 1;
        }

        $appName = getenv('APP_NAME') ?: 'fly-app';
        $appRoot = strtolower($appName);
        $destDir = "{$remoteAppDir}/{$appRoot}";

        $this->output->writeln(' ===== taking off ===>>>>');

        $args = $this->argument('args') ?? [];
        if ($args === []) {
            $args = ['up', '-d'];
        }
        $argsStr = implode(' ', array_map('escapeshellarg', $args));

        $remote = <<<BASH
            get_random_port() {
                LOW_BOUND=49152
                RANGE=16384
                while true; do
                    CANDIDATE=\$((\$LOW_BOUND + (\$RANDOM % \$RANGE)))
                    (echo -n >/dev/tcp/127.0.0.1/\${CANDIDATE}) >/dev/null 2>&1
                    if [ \$? -ne 0 ]; then
                        echo \$CANDIDATE
                        break
                    fi
                done
            }
            export APP_PORT=\$(get_random_port)
            export FORWARD_DB_PORT=\$(get_random_port)
            export FORWARD_REDIS_PORT=\$(get_random_port)
            export FORWARD_VALKEY_PORT=\$(get_random_port)
            export FORWARD_MONGODB_PORT=\$(get_random_port)
            export FORWARD_PGSQL_PORT=\$(get_random_port)
            export VITE_PORT=\$(get_random_port)
            export PUSHER_PORT=\$(get_random_port)
            export PUSHER_METRICS_PORT=\$(get_random_port)
            export FORWARD_TYPESENSE_PORT=\$(get_random_port)
            export ODOO_APP_PORT=\$(get_random_port)
            export ODOO_LONG_POLLING=\$(get_random_port)
            export FORWARD_MINIO_PORT=\$(get_random_port)
            export FORWARD_MINIO_CONSOLE_PORT=\$(get_random_port)
            export FORWARD_MEMCACHED_PORT=\$(get_random_port)
            export FORWARD_MEILISEARCH_PORT=\$(get_random_port)
            export WWWUSER=\$(id -u)
            export WWWGROUP=\$(id -g)
            mkdir -p {$destDir} && cd {$destDir} && \\
            (docker compose {$argsStr} 2>/dev/null || docker-compose {$argsStr})
        BASH;

        $sshKey = getenv('FLY_SSH_KEY') ?: '';
        $sshCmd = ['ssh', '-o', 'StrictHostKeyChecking=no'];
        if ($sshKey) {
            array_splice($sshCmd, 1, 0, ['-i', $sshKey]);
        }
        $sshCmd[] = $sshUser;
        $sshCmd[] = $remote;

        return $this->runProcess($sshCmd);
    }
}
