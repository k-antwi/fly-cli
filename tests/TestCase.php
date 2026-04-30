<?php

namespace Tests;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;
use Symfony\Component\Console\Application as ConsoleApplication;

abstract class TestCase extends BaseTestCase
{
    /**
     * Register a command instance in the running Artisan application,
     * replacing any previously registered command with the same name.
     * Necessary because commands are cached at bootstrap time.
     */
    protected function registerCommand(Command $command): void
    {
        $kernel = $this->app->make(Kernel::class);

        // Traverse the class hierarchy to find the protected `artisan` property.
        $ref = new \ReflectionObject($kernel);
        $artisan = null;
        do {
            if ($ref->hasProperty('artisan')) {
                $prop = $ref->getProperty('artisan');
                $prop->setAccessible(true);
                $artisan = $prop->getValue($kernel);
                break;
            }
        } while ($ref = $ref->getParentClass());

        if ($artisan instanceof ConsoleApplication) {
            $artisan->add($command);
        }
    }
}
