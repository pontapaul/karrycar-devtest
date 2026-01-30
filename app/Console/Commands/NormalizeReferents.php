<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
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
    protected $signature = 'referents:normalize {--skip-backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop duplicate referents and normalize DB structure';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        if (!$this->option('skip-backup')) {
            $backupPath = $this->backupReferentTables();
            $this->info("[`referents`, `referent_shipment`] backup created at: {$backupPath}");
        }

        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $mappedIds = $this->createTemporaryMapTable($connection);

            $this->info("Mapped {$mappedIds} duplicated referents.");

            $changedRows = $this->replaceForeignKeys($connection);

            $this->info("{$changedRows} foreign keys updated in `referent_shipment`.");

            $deletedPivotRows = $this->deleteDuplicatedPivotRows($connection);

            $this->info("{$deletedPivotRows} duplicated rows deleted in `referent_shipment`.");

            $deletedReferents = $this->deleteDuplicatedReferents($connection);

            $this->info("{$deletedReferents} duplicated referents deleted in `referents`.");

            $connection->commit();

        } catch (\Throwable $t) {
            $connection->rollBack();

            $this->error('There was an error normalizing referents: ' . $t->getMessage());
        }

        try {
            $this->addReferentsUniqueConstraint();
        } catch (\Throwable $t) {
            $this->error('There was an error adding referents UNIQUE(email, team_id) constraint: ' . $t->getMessage());
        }
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
            throw new \RuntimeException('mysqldump failed: ' . $process->getErrorOutput());
        }

        Storage::put($path, $process->getOutput());

        return Storage::path($path);
    }

    protected function createTemporaryMapTable(Connection $connection): int
    {
        $success = $connection->statement("
            CREATE TEMPORARY TABLE referents_map (
                old_id BIGINT UNSIGNED PRIMARY KEY,
                new_id BIGINT UNSIGNED NOT NULL,
                INDEX (new_id)
            )
        ");

        if(!$success) {
            throw new \RuntimeException('Referents map could not be created.');
        }

        $success = $connection->insert("
            INSERT INTO referents_map (old_id, new_id)
            SELECT r.id AS old_id,
                   k.new_id
            FROM referents r
            JOIN (
                SELECT email, team_id, MAX(id) AS new_id
                FROM referents
                GROUP BY email, team_id
            ) k
              ON k.email = r.email
             AND k.team_id = r.team_id
            WHERE r.id <> k.new_id
        ");

        if(!$success) {
            throw new \RuntimeException('Referents map could not be populated.');
        }

        return intval($connection->selectOne('SELECT COUNT(*) AS c FROM referents_map')->c);
    }

    protected function replaceForeignKeys(Connection $connection): int
    {
        return $connection->update("
            UPDATE referent_shipment rs
            JOIN referents_map m ON m.old_id = rs.referent_id
            SET rs.referent_id = m.new_id
        ");
    }

    protected function deleteDuplicatedPivotRows(Connection $connection): int
    {
        return $connection->delete("
            DELETE rs1
            FROM referent_shipment rs1
            JOIN referent_shipment rs2
                ON rs1.shipment_id = rs2.shipment_id
                AND rs1.referent_id  = rs2.referent_id
                AND rs1.scope  = rs2.scope
                AND rs1.id < rs2.id
        ");
    }

    protected function deleteDuplicatedReferents(Connection $connection): int
    {
        return $connection->delete("
            DELETE r
            FROM referents r
            JOIN referents_map m
                ON m.old_id = r.id
        ");
    }

    protected function addReferentsUniqueConstraint(): bool
    {
        $success = DB::statement("
            ALTER TABLE referents
                ADD UNIQUE referents_email_team_id_unique (email, team_id)
        ");

        if(!$success) {
            throw new \RuntimeException('Referents unique constraint could not be added.');
        }

        return true;
    }
}
