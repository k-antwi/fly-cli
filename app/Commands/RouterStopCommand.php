<?php

namespace App\Commands;

use App\Concerns\ManagesRouter;
use LaravelZero\Framework\Commands\Command;

class RouterStopCommand extends Command
{
    use ManagesRouter;

    protected $signature = 'router:stop';

    protected $description = 'Stop the global Traefik router';

    public function handle(): int
    {
        if (! file_exists($this->routerComposePath())) {
            $this->output->writeln('Router is not installed. Run <options=bold>fly router:start</> first.');

            return 0;
        }

        $code = $this->stopRouter();

        if ($code === 0) {
            $this->output->writeln('Fly router stopped.');
        }

        return $code;
    }
}
