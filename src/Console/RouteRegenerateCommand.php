<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Console;

use Birdcar\LabelTree\Services\RouteGenerator;
use Illuminate\Console\Command;

class RouteRegenerateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-tree:route:regenerate
        {--force : Skip confirmation in production}';

    /** @var string */
    protected $description = 'Regenerate all label routes from relationships';

    public function handle(RouteGenerator $generator): int
    {
        if (! $this->option('force') && app()->environment('production')) {
            if (! $this->confirm('This will regenerate all routes. Continue?')) {
                $this->info('Cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info('Regenerating routes...');

        $routes = $generator->generateAll();

        $this->info("Routes regenerated. {$routes->count()} new route(s) created.");

        return Command::SUCCESS;
    }
}
