<?php

namespace App\Commands;

use App\Concerns\InteractsWithDockerComposeServices;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class GenNginxConfCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'gen:nginx-conf
        {--ip= : The host IP address the server should listen on}
        {--domain= : The domain name (server_name)}
        {--upstream= : The upstream block name (e.g. the app name)}
        {--letsencrypt : Enable Let\'s Encrypt SSL certificate paths}
        {--no-letsencrypt : Disable Let\'s Encrypt SSL certificate paths}
        {--force : Overwrite the conf file if it already exists}';

    protected $description = 'Generate an nginx site configuration file in docker/nginx';

    public function handle(): int
    {
        $ip = $this->option('ip') ?: text(
            label: 'Host IP address to listen on',
            placeholder: '203.0.113.10',
            required: true,
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_IP) ? null : 'Please enter a valid IP address.',
        );

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->components->error("Invalid IP address [{$ip}].");

            return 1;
        }

        $domain = $this->option('domain') ?: text(
            label: 'Domain name (server_name)',
            placeholder: 'example.com',
            required: true,
        );

        $upstream = $this->option('upstream') ?: text(
            label: 'Upstream / app name',
            placeholder: $this->defaultUpstreamFor($domain),
            default: $this->defaultUpstreamFor($domain),
            required: true,
        );

        if ($this->option('letsencrypt')) {
            $letsencrypt = true;
        } elseif ($this->option('no-letsencrypt')) {
            $letsencrypt = false;
        } else {
            $letsencrypt = confirm(
                label: "Generate Let's Encrypt SSL certificate paths for {$domain}?",
                default: true,
            );
        }

        $stub = file_get_contents($this->resourcePath('stubs/nginx-conf.stub'));

        $contents = strtr($stub, [
            '<ip_address>' => $ip,
            '<domain>' => $domain,
            '<upstream>' => $upstream,
            '<ssl_open>' => $letsencrypt ? '' : '# ',
        ]);

        $dir = $this->projectPath('docker/nginx');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $target = $dir.'/'.$domain.'.conf';

        if (file_exists($target) && ! $this->option('force')) {
            if (! confirm(label: "{$target} already exists. Overwrite?", default: false)) {
                $this->components->warn('Aborted; existing file left unchanged.');

                return 1;
            }
        }

        file_put_contents($target, $contents);

        $this->output->writeln('');
        $this->components->info("Nginx configuration generated at docker/nginx/{$domain}.conf");

        if ($letsencrypt) {
            $this->components->warn(
                "Make sure {$domain} resolves to {$ip} and that Let's Encrypt certificates exist at /etc/letsencrypt/live/{$domain}/ before reloading nginx."
            );
        }

        return 0;
    }

    private function defaultUpstreamFor(string $domain): string
    {
        $base = strtolower(explode('.', $domain)[0] ?? 'app');
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base);

        return trim($base, '_') ?: 'app';
    }
}
