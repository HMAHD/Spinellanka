<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 128)->nullable();
            $table->string('actor_email', 191)->nullable();
            $table->string('actor_type', 32)->default('staff');
            $table->string('action', 64);
            $table->nullableMorphs('auditable');
            $table->string('summary', 255);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'action']);
        });

        // Add database-level immutability triggers
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_audit_events_prevent_update
                BEFORE UPDATE ON audit_events
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'audit_events is append-only: updates are prohibited');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_events_prevent_delete
                BEFORE DELETE ON audit_events
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'audit_events is append-only: deletions are prohibited');
                END;
            ");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("
                CREATE TRIGGER trg_audit_events_prevent_update
                BEFORE UPDATE ON audit_events
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'audit_events is append-only: updates are prohibited';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_events_prevent_delete
                BEFORE DELETE ON audit_events
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'audit_events is append-only: deletions are prohibited';
                END;
            ");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_prevent_delete');
        }

        Schema::dropIfExists('audit_events');
    }
};
