<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : How many days of backups to keep} {--connection= : Database connection (default: the default one)}';

    protected $description = 'Make a compressed copy of the database in storage/app/backups (without the big import tables)';

    /** Tables that can be filled again from the RDW or that only hold temporary data: not worth backing up. */
    private const SKIP = ['staging_vehicles', 'staging_defects', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $db = config('database.connections.'.($this->option('connection') ?: config('database.default')));
        $directory = storage_path('app/backups');
        @mkdir($directory, 0775, true);

        $file = $directory.'/'.now()->format('Y-m-d_His').'.sql';

        $command = [
            'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--result-file='.$file,
            ...array_map(fn ($table) => "--ignore-table={$db['database']}.{$table}", self::SKIP),
            $db['database'],
        ];

        // the password goes in the environment, not on the command line where other users could see it
        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])->timeout(600)->run($command);

        if ($result->failed() || ! is_file($file)) {
            $this->error('mysqldump failed: '.trim($result->errorOutput()));
            @unlink($file);

            return self::FAILURE;
        }

        $this->compress($file);
        $this->prune($directory, (int) $this->option('keep'));
        $this->info("Backup saved: {$file}.gz");

        return self::SUCCESS;
    }

    private function compress(string $file): void
    {
        $in = fopen($file, 'rb');
        $out = gzopen($file.'.gz', 'wb9');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
        unlink($file);
    }

    /** Delete backups older than $days days. */
    private function prune(string $directory, int $days): void
    {
        foreach (glob($directory.'/*.sql.gz') ?: [] as $old) {
            if (filemtime($old) < now()->subDays($days)->getTimestamp()) {
                unlink($old);
            }
        }
    }
}
