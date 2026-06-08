<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class MoveToMysql extends Command
{
    protected $signature = 'db:move-to-mysql
                            {--sqlite= : Path to the SQLite file (default: database/database.sqlite)}
                            {--fresh : Run migrate:fresh before importing (recommended)}
                            {--seed : Run RolesAndPermissionsSeeder after import if roles are missing}';

    protected $description = 'Migrate schema to MySQL and copy existing data from SQLite';

    /** @var list<string> */
    private array $tableOrder = [
        'departments',
        'users',
        'permissions',
        'roles',
        'role_has_permissions',
        'model_has_permissions',
        'model_has_roles',
        'documents',
        'routing_rules',
        'document_route_steps',
        'document_scans',
        'document_attachments',
        'department_notifications',
        'activity_log',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Set DB_CONNECTION=mysql in .env before running this command.');

            return self::FAILURE;
        }

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to MySQL: '.$e->getMessage());
            $this->line('Create the database first: ./scripts/setup-mysql.sh');

            return self::FAILURE;
        }

        $sqlitePath = $this->option('sqlite') ?: database_path('database.sqlite');
        if (! is_file($sqlitePath)) {
            $this->error("SQLite file not found: {$sqlitePath}");

            return self::FAILURE;
        }

        config([
            'database.connections.sqlite_legacy' => [
                'driver' => 'sqlite',
                'database' => $sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        if ($this->option('fresh')) {
            $this->warn('Running migrate:fresh on MySQL (this clears any existing MySQL data).');
            if (! $this->confirm('Continue?', true)) {
                return self::SUCCESS;
            }
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->info(Artisan::output());
        } else {
            Artisan::call('migrate', ['--force' => true]);
            $this->info(Artisan::output());
        }

        $sqliteTables = collect(DB::connection('sqlite_legacy')
            ->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->all();

        $tables = array_values(array_unique(array_merge(
            $this->tableOrder,
            array_diff($sqliteTables, $this->tableOrder, ['migrations']),
        )));

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');

        $imported = 0;
        foreach ($tables as $table) {
            if ($table === 'migrations' || ! in_array($table, $sqliteTables, true)) {
                continue;
            }

            if (! $this->mysqlTableExists($table)) {
                $this->line("  skip {$table} (not in MySQL schema)");

                continue;
            }

            $rows = DB::connection('sqlite_legacy')->table($table)->get();
            if ($rows->isEmpty()) {
                continue;
            }

            DB::connection('mysql')->table($table)->truncate();
            foreach ($rows->chunk(100) as $chunk) {
                DB::connection('mysql')->table($table)->insert(
                    $chunk->map(fn ($row) => (array) $row)->all()
                );
            }

            $count = $rows->count();
            $imported += $count;
            $this->line("  {$table}: {$count} row(s)");
        }

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('seed') && DB::connection('mysql')->table('roles')->count() === 0) {
            Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
            $this->info('Seeded roles and permissions.');
        }

        $this->newLine();
        $this->info("Done. Imported {$imported} row(s) into MySQL.");
        $this->line('Verify: php artisan tinker --execute="echo \\App\\Models\\Document::count();"');

        return self::SUCCESS;
    }

    private function mysqlTableExists(string $table): bool
    {
        return DB::connection('mysql')->getSchemaBuilder()->hasTable($table);
    }
}
