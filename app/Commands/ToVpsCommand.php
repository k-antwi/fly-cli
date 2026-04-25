<?php

namespace App\Commands;

use App\Concerns\ProxiesDocker;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class ToVpsCommand extends Command
{
    use ProxiesDocker;

    protected $signature = 'to:vps
        {--with= : Source directory to ship (defaults to ".")}';

    protected $description = 'Ship the application source to a remote VPS over SSH';

    /** @var array<int,string> */
    private array $excludeDefaults = [
        './storage', './node_modules', './tests', './vendor', './.env',
    ];

    public function handle(): int
    {
        $this->loadDotEnv();

        $sshUser = getenv('FLY_SSH_USERNAME');
        if (! $sshUser) {
            $this->output->writeln('<error>SSH username is required. Please set the FLY_SSH_USERNAME environment variable.</error>');

            return 1;
        }

        $destDir = getenv('FLY_REMOTE_APP_DIR');
        if (! $destDir) {
            $this->output->writeln('<error>Remote app directory is required. Please set the FLY_REMOTE_APP_DIR environment variable.</error>');

            return 1;
        }

        $sourceDir = $this->option('with') ?: '.';

        $appName = getenv('APP_NAME') ?: 'fly-app';
        $appRoot = strtolower($appName);
        $archive = "{$appRoot}.tar.gz";

        $this->maybeCloneEnvForProduction();

        $this->output->writeln(' ===== taking off ===>>>>');
        $this->output->writeln("to >>> {$sourceDir} {$sshUser}:{$destDir}");

        $excludes = $this->excludeDefaults;
        if (is_dir($this->projectPath('.git'))) {
            $excludes[] = './.git';
        } elseif (is_dir($this->projectPath('.github'))) {
            $excludes[] = './.github';
        }
        $excludes[] = "./{$archive}";

        $tarArgs = ['tar', '-czvf', $archive, '-C', $sourceDir];
        foreach ($excludes as $ex) {
            $tarArgs[] = "--exclude={$ex}";
        }
        $tarArgs[] = '.';

        $this->output->writeln('==>>> Compressing files...');
        $this->output->writeln('===>> Excluding: '.implode(' ', $excludes));

        if (($exit = $this->runProcess($tarArgs)) !== 0) {
            return $exit;
        }

        $sshKey = getenv('FLY_SSH_KEY') ?: '';
        $scpArgs = ['scp'];
        if ($sshKey) {
            $scpArgs[] = '-i';
            $scpArgs[] = $sshKey;
        }
        $scpArgs[] = '-r';
        $scpArgs[] = $archive;
        $scpArgs[] = "{$sshUser}:{$destDir}";

        $scpExit = $this->runProcess($scpArgs);

        if ($scpExit === 0) {
            @unlink($this->projectPath($archive));
            $this->output->writeln('==>>> Decompressing files ...');

            $remote = "
                mkdir -p {$destDir}/{$appRoot} && \\
                tar --warning=no-unknown-keyword -xzvf {$destDir}/{$archive} -C {$destDir}/{$appRoot} && \\
                rm {$destDir}/{$archive} && \\
                cd {$destDir}/{$appRoot} && \\
                mkdir -p storage/framework/cache && \\
                mkdir -p storage/logs && \\
                mkdir -p storage/framework/sessions && \\
                mkdir -p storage/framework/views && \\
                chmod -R 775 storage bootstrap/cache
            ";

            $sshCmd = ['ssh'];
            if ($sshKey) {
                $sshCmd[] = '-i';
                $sshCmd[] = $sshKey;
            }
            $sshCmd[] = $sshUser;
            $sshCmd[] = $remote;

            $this->runProcess($sshCmd);
            $this->output->writeln('Files copied successfully.');
        } else {
            $this->output->writeln('<error>Error occurred while copying files.</error>');
            @unlink($this->projectPath($archive));
        }

        $this->output->writeln(' <<<<===== landed safely! ===');

        return $scpExit;
    }

    private function maybeCloneEnvForProduction(): void
    {
        $flyDir = $this->projectPath('.fly');
        if (! is_dir($flyDir)) {
            @mkdir($flyDir, 0755, true);
        }

        $env = $this->projectPath('.env');
        $prodEnv = $flyDir.'/.env.production';

        if (file_exists($env) && ! file_exists($prodEnv)) {
            @copy($env, $prodEnv);
        }
    }
}
