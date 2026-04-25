<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class BinCommand extends ContainerCommand
{
    protected $signature = 'bin {cmd : The vendor/bin script to run} {args?*}';

    protected $description = 'Run a Composer vendor/bin script inside the application container';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $tokens = $this->forwardedTokens();
        if ($tokens === []) {
            $this->output->writeln('<error>Usage: fly bin <script> [args...]</error>');

            return 1;
        }

        $script = array_shift($tokens);

        return $this->composeExec(array_merge(['./vendor/bin/'.$script], $tokens));
    }
}
