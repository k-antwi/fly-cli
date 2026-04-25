<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class RunCommand extends ContainerCommand
{
    protected $signature = 'run {cmd : The command to run} {args?*}';

    protected $description = 'Run an arbitrary command inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $tokens = $this->forwardedTokens();
        if ($tokens === []) {
            $this->output->writeln('<error>Usage: fly run <command> [args...]</error>');

            return 1;
        }

        return $this->composeExec($tokens);
    }
}
