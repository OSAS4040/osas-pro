<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SystemToolsCommand extends Command
{
    protected $signature = 'system:tools
                            {action=list : list | run}
                            {name? : Script basename (e.g. check_tables) when action=run}';

    protected $description = 'List or safely run dev-only PHP scripts from tools/dev (blocked in production).';

    public function handle(): int
    {
        $toolsDir = base_path('tools/dev');

        if (! is_dir($toolsDir)) {
            $this->error('Directory not found: '.$toolsDir);

            return 1;
        }

        $action = $this->argument('action');

        if ($action === 'list') {
            return $this->listTools($toolsDir);
        }

        if ($action === 'run') {
            return $this->runTool($toolsDir);
        }

        $this->error('Unknown action. Use "list" or "run".');

        return 1;
    }

    private function listTools(string $toolsDir): int
    {
        $files = glob($toolsDir.'/*.php') ?: [];

        if ($files === []) {
            $this->warn('No .php scripts in '.$toolsDir);

            return 0;
        }

        sort($files);

        $rows = [];
        foreach ($files as $path) {
            $rows[] = [basename($path), $path];
        }

        $this->table(['Script', 'Path'], $rows);
        $this->line('');
        $this->line('Run: php artisan system:tools run <name>');
        $this->line('Example: php artisan system:tools run check_tables');

        return 0;
    }

    private function runTool(string $toolsDir): int
    {
        if (app()->environment('production')) {
            $this->error('Running dev tools is disabled in production.');

            return 1;
        }

        $name = $this->argument('name');
        if (! $name) {
            $this->error('Provide a script name: php artisan system:tools run <name>');

            return 1;
        }

        $name = str_replace(['..', '/', '\\'], '', $name);
        if (! str_ends_with($name, '.php')) {
            $name .= '.php';
        }

        $path = $toolsDir.DIRECTORY_SEPARATOR.$name;

        if (! is_file($path)) {
            $this->error('Script not found: '.$path);

            return 1;
        }

        $this->info('Executing: '.$path);

        $process = new Process([PHP_BINARY, $path], base_path(), null, null, 600);
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Exit code: '.$process->getExitCode());

            return (int) $process->getExitCode() ?: 1;
        }

        return 0;
    }
}
