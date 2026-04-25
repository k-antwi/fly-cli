<?php

namespace App\Commands;

use App\Commands\Concerns\ContainerCommand;

class OpenCommand extends ContainerCommand
{
    protected $signature = 'open';

    protected $description = 'Open the application URL in your browser';

    public function handle(): int
    {
        if (($code = $this->boot()) !== 0) {
            return $code;
        }

        $opener = $this->resolveOpener();

        if ($opener === null) {
            $this->output->writeln('<error>Neither "open" nor "xdg-open" is available.</error>');

            return 1;
        }

        $appUrl = getenv('APP_URL') ?: 'http://localhost';
        $appPort = getenv('APP_PORT');
        $url = ($appPort && $appPort !== '80') ? "{$appUrl}:{$appPort}" : $appUrl;

        return $this->runProcess([$opener, $url]);
    }

    private function resolveOpener(): ?string
    {
        foreach (['open', 'xdg-open'] as $candidate) {
            $which = new \Symfony\Component\Process\Process(['which', $candidate]);
            $which->run();
            if ($which->isSuccessful() && trim($which->getOutput()) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
