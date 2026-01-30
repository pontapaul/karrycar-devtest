<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use const Grpc\STATUS_OUT_OF_RANGE;

class RollbackReferents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referents:rollback {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore referents tables from the latest backup or a specified file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->resolveBackupFile();
        $absolutePath = Storage::path($path);

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $command = [
            'mysql',
            '-h', $config['host'] ?? '127.0.0.1',
            '-P', (string) ($config['port'] ?? 3306),
            '-u', $config['username'] ?? 'root',
        ];

        if (!empty($config['password'])) {
            $command[] = '-p' . $config['password'];
        }

        $command[] = $config['database'];

        $stream = fopen($absolutePath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open backup file: ' . $path);
        }

        $process = new Process($command);
        $process->setInput($stream);
        $process->setTimeout(120);
        $process->run();

        fclose($stream);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('mysql restore failed: ' . $process->getErrorOutput());
        }

        $this->info('Backup restored from: ' . $path);
    }

    private function resolveBackupFile(): string
    {
        $input = $this->argument('file');

        if($input) {
            if(Storage::has('backups/referents/'.$input)) {
                return 'backups/referents/'.$input;
            } else {
                throw new \RuntimeException("Backup file `$input` not found");
            }
        }

        $latestFile = collect(Storage::files('backups/referents'))
            ->filter(fn ($file) => str_ends_with($file, '.mysql'))
            ->sort()
            ->last();

        if (!$latestFile) {
            throw new \RuntimeException('No backup files found.');
        }

        return $latestFile;
    }
}
