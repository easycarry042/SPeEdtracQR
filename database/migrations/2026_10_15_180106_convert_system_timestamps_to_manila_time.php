<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves every system-recorded timestamp from UTC to Philippine time.
 *
 * Until now the app ran on UTC, so "today" turned over at 08:00 local time and
 * the booking calendar marked yesterday as today for the first eight hours of
 * every day. config/app.php now runs on Asia/Manila; without this conversion
 * every record written before the switch would read eight hours early.
 *
 * Only `timestamp` columns move — created_at, read_at, acted_at and the like,
 * which the system stamps itself. `datetime` columns are left alone: the only
 * ones are a booking's starts_at / ends_at, which hold the time staff typed in
 * and were already local.
 *
 * Manila has no daylight saving, so the offset is a constant eight hours.
 * Runs on MySQL/MariaDB and Postgres; SQLite (the in-memory test database)
 * starts empty, so there is nothing to convert.
 */
return new class extends Migration
{
    private const OFFSET_HOURS = 8;

    public function up(): void
    {
        $this->shift(self::OFFSET_HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::OFFSET_HOURS);
    }

    private function shift(int $hours): void
    {
        foreach ($this->timestampColumnsByTable() as $table => $columns) {
            $assignments = collect($columns)
                ->map(fn (string $column): string => $this->shiftExpression($column, $hours))
                ->implode(', ');

            // NULL plus an interval stays NULL, so one UPDATE per table covers
            // every column without a WHERE.
            DB::statement('UPDATE '.DB::getQueryGrammar()->wrapTable($table).' SET '.$assignments);
        }
    }

    private function shiftExpression(string $column, int $hours): string
    {
        $wrapped = DB::getQueryGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'pgsql' => "{$wrapped} = {$wrapped} + interval '{$hours} hours'",
            default => "{$wrapped} = {$wrapped} + INTERVAL {$hours} HOUR",
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function timestampColumnsByTable(): array
    {
        $rows = match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::select(
                "select table_name as tbl, column_name as col from information_schema.columns
                 where table_schema = database() and data_type = 'timestamp'"
            ),
            'pgsql' => DB::select(
                "select table_name as tbl, column_name as col from information_schema.columns
                 where table_schema = current_schema() and data_type = 'timestamp without time zone'"
            ),
            default => [],
        };

        $byTable = [];
        foreach ($rows as $row) {
            $byTable[$row->tbl][] = $row->col;
        }

        return $byTable;
    }
};
