<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->modifySourceEnum("ENUM('web', 'api', 'paygo') NOT NULL DEFAULT 'web'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE verification_requests SET source = 'api' WHERE source = 'paygo'");
        $this->modifySourceEnum("ENUM('web', 'api') NOT NULL DEFAULT 'web'");
    }

    private function modifySourceEnum(string $definition): void
    {
        try {
            DB::statement("ALTER TABLE verification_requests MODIFY source {$definition}");
        } catch (QueryException $exception) {
            if (! $this->isGenericInnoDbAlterError($exception)) {
                throw $exception;
            }

            DB::statement("ALTER TABLE verification_requests MODIFY source {$definition}, ALGORITHM=COPY, LOCK=SHARED");
        }
    }

    private function isGenericInnoDbAlterError(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[1] ?? null) === 1030
            && str_contains((string) ($errorInfo[2] ?? ''), 'Got error 168');
    }
};
