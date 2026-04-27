<?php

namespace App\Commands;

use App\Concerns\ManagesRouter;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class RouterStatusCommand extends Command
{
    use ManagesRouter;

    protected $signature = 'router:status';

    protected $description = 'Show the status of the global Traefik router';

    public function handle(): int
    {
        $networkExists   = $this->routerNetworkExists();
        $containerRunning = $this->routerContainerIsRunning();

        $this->output->writeln('');
        $this->line('  <options=bold>Fly Router Status</>');
        $this->output->writeln('');

        $networkStatus   = $networkExists ? '<fg=green>✓ exists</>' : '<fg=red>✗ not found</>';
        $containerStatus = $containerRunning ? '<fg=green>✓ running</>' : '<fg=red>✗ stopped</>';

        $this->output->writeln("  fly-router network:  {$networkStatus}");
        $this->output->writeln("  Traefik container:   {$containerStatus}");
        $this->output->writeln('');

        if (! $containerRunning) {
            $this->output->writeln('  Run <options=bold>fly router:start</> to start the router.');
            $this->output->writeln('');

            return 0;
        }

        $this->output->writeln("  Dashboard:  <options=bold>http://localhost:8080</>");
        $this->output->writeln("  Domain:     <options=bold>*.".$this->routerDomain()."</>");
        $this->output->writeln('');

        $this->printRegisteredRoutes();

        return 0;
    }

    private function printRegisteredRoutes(): void
    {
        $process = new Process(
            ['curl', '-s', '--connect-timeout', '2', 'http://127.0.0.1:8080/api/http/routers'],
        );
        $process->run();

        if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
            $this->output->writeln('  <fg=gray>Could not reach Traefik API.</fg=gray>');
            $this->output->writeln('');

            return;
        }

        $routers = json_decode($process->getOutput(), true);

        if (! is_array($routers) || empty($routers)) {
            $this->output->writeln('  <fg=gray>No routes registered yet.</fg=gray>');
            $this->output->writeln('');

            return;
        }

        $rows = [];
        foreach ($routers as $router) {
            $name   = $router['name'] ?? '—';
            $rule   = $router['rule'] ?? '—';
            $status = $router['status'] ?? '—';

            if (str_ends_with($name, '@internal')) {
                continue;
            }

            $statusFormatted = $status === 'enabled'
                ? '<fg=green>'.$status.'</>'
                : '<fg=yellow>'.$status.'</>';

            $rows[] = [$name, $rule, $statusFormatted];
        }

        if (empty($rows)) {
            $this->output->writeln('  <fg=gray>No project routes registered yet.</fg=gray>');
            $this->output->writeln('');

            return;
        }

        $this->table(['Router', 'Rule', 'Status'], $rows);
        $this->output->writeln('');
    }
}
