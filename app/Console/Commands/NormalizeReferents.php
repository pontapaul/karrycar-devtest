<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class NormalizeReferents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referents:normalize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop duplicate referents and normalize DB structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $backupPath = $this->backupReferentTables();
        $this->info("[`referents`, `referent_shipment`] backup created in: {$backupPath}");

        //TODO: creare una mappa di tutte le righe duplicate

        DB::beginTransaction();

        //TODO: sostituire le FK con l'id corrispondente nella mappa

        //TODO: rimuovere tutte le righe non più necessarie (valutare se farlo durante la sostituzione)

        //TODO: aggiunta constraint (email, team_id) nella tabella referents

        DB::commit();
    }

    protected function backupReferentTables(): string
    {
        $timestamp = now()->timestamp;
        $path = "backups/referents/{$timestamp}.mysql";
        $tables = ['referents', 'referent_shipment'];

        Storage::makeDirectory('backups/referents');

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $command = [
            'mysqldump',
            '--single-transaction',
            '--skip-lock-tables',
            '-h', $config['host'] ?? '127.0.0.1',
            '-P', (string) ($config['port'] ?? 3306),
            '-u', $config['username'] ?? 'root',
            '-p' . $config['password'],
            $config['database'],
            ...$tables
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('mysqldump error: ' . $process->getErrorOutput());
        }

        Storage::put($path, $process->getOutput());

        return Storage::path($path);
    }
}
