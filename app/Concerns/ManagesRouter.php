<?php

namespace App\Concerns;

use Symfony\Component\Process\Process;

trait ManagesRouter
{
    protected function routerHome(): string
    {
        return (getenv('HOME') ?: '/root').'/.fly/router';
    }

    protected function routerComposePath(): string
    {
        return $this->routerHome().'/docker-compose.yml';
    }

    protected function routerNetworkExists(): bool
    {
        $process = new Process(['docker', 'network', 'inspect', 'fly-router', '--format', '{{.Name}}']);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    protected function routerContainerIsRunning(): bool
    {
        $process = new Process(['docker', 'inspect', '--format', '{{.State.Running}}', 'fly-router-traefik']);
        $process->run();

        return trim($process->getOutput()) === 'true';
    }

    protected function ensureRouterNetworkExists(): void
    {
        if (! $this->routerNetworkExists()) {
            (new Process(['docker', 'network', 'create', 'fly-router']))->run();
        }
    }

    protected function ensureRouterHomeExists(): void
    {
        if (! is_dir($this->routerHome())) {
            mkdir($this->routerHome(), 0755, true);
        }

        if (! file_exists($this->routerComposePath())) {
            $this->writeRouterComposeFile();
        }
    }

    protected function writeRouterComposeFile(): void
    {
        file_put_contents($this->routerComposePath(), $this->routerComposeContents());
    }

    protected function startRouter(): int
    {
        $this->ensureRouterHomeExists();
        $this->ensureRouterNetworkExists();

        $bin = $this->resolveRouterComposeBin();
        $process = new Process(
            array_merge($bin, ['-f', $this->routerComposePath(), 'up', '-d', '--pull', 'missing']),
            $this->routerHome()
        );

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }

    protected function stopRouter(): int
    {
        $bin = $this->resolveRouterComposeBin();
        $process = new Process(
            array_merge($bin, ['-f', $this->routerComposePath(), 'down']),
            $this->routerHome()
        );

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }

    protected function routerDomain(): string
    {
        return getenv('FLY_ROUTER_DOMAIN') ?: 'localhost';
    }

    protected function sanitiseHostname(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[_\s]+/', '-', $name);
        $name = preg_replace('/[^a-z0-9-]/', '', $name);
        $name = trim($name, '-');

        return substr($name, 0, 63) ?: 'fly-app';
    }

    private function resolveRouterComposeBin(): array
    {
        $check = new Process(['docker', 'compose', 'version']);
        $check->run();

        return $check->isSuccessful() ? ['docker', 'compose'] : ['docker-compose'];
    }

    private function routerComposeContents(): string
    {
        return <<<'YAML'
# Managed by fly — regenerate with: fly router:start --force
services:
  traefik:
    image: traefik:v3
    container_name: fly-router-traefik
    restart: unless-stopped
    command:
      - "--log.level=WARN"
      - "--api.insecure=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--providers.docker.network=fly-router"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--entrypoints.traefik.address=:8080"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--entrypoints.web.http.redirections.entrypoint.scheme=https"
      - "--entrypoints.web.http.redirections.entrypoint.permanent=true"
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    volumes:
      - "/var/run/docker.sock:/var/run/docker.sock:ro"
    networks:
      - fly-router

networks:
  fly-router:
    external: true
    name: fly-router
YAML;
    }
}
