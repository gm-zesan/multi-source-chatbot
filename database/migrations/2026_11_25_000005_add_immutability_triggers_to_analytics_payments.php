<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces database-level ledger immutability using MySQL/SQLite triggers.
     * Prevents raw SQL mutations (UPDATE/DELETE) from bypassing Eloquent model events.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_update;');
            DB::unprepared("
                CREATE TRIGGER trg_analytics_payments_prevent_update
                BEFORE UPDATE ON analytics_payments
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'AnalyticsPayment records are strictly immutable and cannot be updated.';
                END;
            ");

            DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_delete;');
            DB::unprepared("
                CREATE TRIGGER trg_analytics_payments_prevent_delete
                BEFORE DELETE ON analytics_payments
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'AnalyticsPayment records are strictly immutable and cannot be deleted.';
                END;
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_update;');
            DB::unprepared("
                CREATE TRIGGER trg_analytics_payments_prevent_update
                BEFORE UPDATE ON analytics_payments
                BEGIN
                    SELECT RAISE(ABORT, 'AnalyticsPayment records are strictly immutable and cannot be updated.');
                END;
            ");

            DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_delete;');
            DB::unprepared("
                CREATE TRIGGER trg_analytics_payments_prevent_delete
                BEFORE DELETE ON analytics_payments
                BEGIN
                    SELECT RAISE(ABORT, 'AnalyticsPayment records are strictly immutable and cannot be deleted.');
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_analytics_payments_prevent_delete;');
    }
};
