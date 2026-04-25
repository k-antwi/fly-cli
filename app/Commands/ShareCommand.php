<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class ShareCommand extends ContainerCommand
{
    protected $signature = 'share {args?*}';

    protected $description = 'Share the application publicly via a temporary URL';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $appPort = getenv('APP_PORT');
        $dashboard = getenv('FLY_SHARE_DASHBOARD') ?: '4040';
        $serverHost = getenv('FLY_SHARE_SERVER_HOST') ?: 'laravel-fly.site';
        $serverPort = getenv('FLY_SHARE_SERVER_PORT') ?: '8080';
        $token = getenv('FLY_SHARE_TOKEN') ?: '';
        $server = getenv('FLY_SHARE_SERVER') ?: '';
        $subdomain = getenv('FLY_SHARE_SUBDOMAIN') ?: '';
        $domain = getenv('FLY_SHARE_DOMAIN') ?: $serverHost;

        $argv = array_merge([
            'docker', 'run', '--init', '--rm',
            '--add-host=host.docker.internal:host-gateway',
            '-p', "{$dashboard}:4040",
            '-t', 'beyondcodegmbh/expose-server:latest',
            'share', "http://host.docker.internal:{$appPort}",
            "--server-host={$serverHost}",
            "--server-port={$serverPort}",
            "--auth={$token}",
            "--server={$server}",
            "--subdomain={$subdomain}",
            "--domain={$domain}",
        ], $this->forwardedTokens());

        return $this->runProcess($argv);
    }
}
