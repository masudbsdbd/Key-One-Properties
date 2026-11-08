<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'keyone:backup {--keep=14 : Number of daily backups to keep}';

    protected $description = 'Dump the MySQL database to storage/app/private/backups as a gzip file';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));
        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Only MySQL is supported.');

            return self::FAILURE;
        }

        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir, 0750);
        $file = $dir.'/'.$config['database'].'-'.now()->format('Ymd-His').'.sql.gz';

        // Credentials go through the environment, not the command line, so they do not appear in the process list.
        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])
            ->timeout(3600)
            ->run(sprintf(
                'mysqldump --single-transaction --quick --routines --no-tablespaces -h %s -P %s -u %s %s | gzip > %s',
                escapeshellarg($config['host']),
                escapeshellarg((string) $config['port']),
                escapeshellarg($config['username']),
                escapeshellarg($config['database']),
                escapeshellarg($file),
            ));

        if (! $result->successful() || ! is_file($file) || filesize($file) < 100) {
            File::delete($file);
            $this->error('Backup failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        collect(File::glob($dir.'/*.sql.gz'))->sortDesc()->slice((int) $this->option('keep'))->each(fn ($old) => File::delete($old));
        $this->info('Backup written: '.basename($file));

        return self::SUCCESS;
    }
}
