<?php

namespace App\Commands;

use App\Concerns\ManagesRouter;
use LaravelZero\Framework\Commands\Command;

class RouterStartCommand extends Command
{
    use ManagesRouter;

    protected $signature = 'router:start {--force : Regenerate the Traefik compose file}';

    protected $description = 'Start the global Traefik router';

    public function handle(): int
    {
        if (! $this->dockerIsRunning()) {
            $this->output->writeln('<error>Docker is not running.</error>');

            return 1;
        }

        if ($this->option('force') && file_exists($this->routerComposePath())) {
            unlink($this->routerComposePath());
        }

        $code = $this->startRouter();

        if ($code !== 0) {
            $this->output->writeln('<error>Failed to start the fly router.</error>');

            return $code;
        }

        $domain = $this->routerDomain();

        $this->output->writeln('');
        $this->output->writeln('  <info>INFO</info>  Fly router started.');
        $this->output->writeln('');
        $this->output->writeln("  Dashboard:  <options=bold>http://localhost:8080</>");
        $this->output->writeln("  Domain:     <options=bold>*.$domain</>");
        $this->output->writeln('');

        if ($domain === 'localhost') {
            $this->output->writeln('  <fg=gray>.localhost domains work natively in Chrome, Firefox, and Safari.</fg=gray>');
            $this->output->writeln('  <fg=gray>No DNS configuration required.</fg=gray>');
        } else {
            $this->output->writeln('  <fg=gray>For .test domains, configure dnsmasq:</fg=gray>');
            $this->output->writeln('  <fg=gray>  brew install dnsmasq</fg=gray>');
            $this->output->writeln('  <fg=gray>  echo \'address=/.test/127.0.0.1\' | sudo tee /etc/resolver/test</fg=gray>');
        }

        $this->output->writeln('');

        return 0;
    }
}
