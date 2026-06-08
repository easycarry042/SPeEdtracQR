<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SlaQuery
{
    /**
     * Raw SQL comparing an IN-scan's age against the current department SLA.
     * SQLite uses julianday(); MySQL/MariaDB use TIMESTAMPDIFF.
     */
    public static function scanOverdueHoursSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => '(julianday("now") - julianday(document_scans.scanned_at)) * 24 > (select sla_hours from departments where id = documents.current_department_id)',
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(HOUR, document_scans.scanned_at, NOW()) > (SELECT sla_hours FROM departments WHERE id = documents.current_department_id)',
            default => throw new \RuntimeException('Unsupported database driver for SLA overdue filter: '.DB::connection()->getDriverName()),
        };
    }
}
