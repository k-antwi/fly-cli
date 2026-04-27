<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;
use App\Concerns\ManagesRouter;

class UpCommand extends ContainerCommand
{
    use ManagesRouter;

    protected $signature = 'up {args?* : Arguments forwarded to "docker compose up"}';

    protected $description = 'Start the application containers';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        if (! $this->routerNetworkExists()) {
            $this->output->writeln('<fg=gray>Initialising fly router...</>');
            $this->startRouter();
        } elseif (! $this->routerContainerIsRunning()) {
            $this->output->writeln('<fg=gray>Restarting fly router...</>');
            $this->startRouter();
        }

        return $this->dockerComposePassthrough(array_merge(['up'], $this->forwardedTokens()));
    }
}
