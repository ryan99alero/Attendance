<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        // 1) EMPLOYEES TABLE (shift_id has NO FK — denormalized/cache)
        Schema::create('employees', function (Blueprint $table) {
            $table->id()->comment('Primary key of the employees table');

            $table->string('external_id', 255)
                ->nullable()
                ->comment('External system identifier for the employee');

            $table->unsignedBigInteger('department_id')
                ->nullable()
                ->comment('Foreign key referencing the departments table');

            $table->string('email', 255)
                ->nullable()
                ->comment('Employee email address');

            $table->string('first_name', 50)
                ->comment('First name of the employee');

            $table->string('last_name', 50)
                ->comment('Last name of the employee');

            $table->string('address', 255)
                ->nullable()
                ->comment('Residential address of the employee');

            $table->string('city', 255)
                ->nullable()
                ->comment('City of residence of the employee');

            $table->string('state', 255)
                ->nullable()
                ->comment('State of residence of the employee');

            $table->string('zip', 255)
                ->nullable()
                ->comment('ZIP or postal code of the employee');

            $table->string('country', 255)
                ->nullable()
                ->comment('Country of residence of the employee');

            $table->string('phone', 15)
                ->nullable()
                ->comment('Contact phone number of the employee');

            // NOTE: shift_id kept as a plain unsigned big int (no FK) — cache/denormalized from shift_schedules
            $table->unsignedBigInteger('shift_id')
                ->nullable()
                ->comment('Cached/denormalized shift id (kept for quick reference; not enforced by FK)');

            $table->string('photograph', 255)
                ->nullable()
                ->comment('Path or URL of the employee photograph');

            $table->date('termination_date')
                ->nullable()
                ->comment('Date of termination, if applicable');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Indicates if the employee is currently active');

            $table->boolean('full_time')
                ->default(false)
                ->comment('Indicates if the employee is a full-time worker');

            $table->boolean('vacation_pay')
                ->default(false)
                ->comment('Indicates if the employee is eligible for vacation pay');

            $table->unsignedBigInteger('created_by')
                ->nullable()
                ->comment('Foreign key referencing the user who created the record');

            $table->unsignedBigInteger('updated_by')
                ->nullable()
                ->comment('Foreign key referencing the user who last updated the record');

            $table->timestamps();


            $table->string('full_names', 101)
                ->nullable()
                ->comment('Concatenated full name of the employee');

            $table->unsignedBigInteger('shift_schedule_id')
                ->nullable()
                ->comment('Foreign key referencing the shift schedules table');

            $table->unsignedBigInteger('round_group_id')
                ->nullable()
                ->comment('Foreign key referencing the round_groups table');

            // Foreign key constraints (NOTE: NO FK on shift_id by design)
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')->on('users')
                ->onDelete('set null');


            $table->foreign('shift_schedule_id')
                ->references('id')->on('shift_schedules')
                ->onDelete('set null');

            $table->foreign('round_group_id')
                ->references('id')->on('round_groups')
                ->onDelete('set null')
                ->onUpdate('cascade');

            // Indexes
            $table->index(['first_name', 'last_name'], 'idx_employee_name');
            $table->index('department_id', 'idx_department_id');
        });

        // 2) TRIGGERS for full_names
        DB::unprepared("
            CREATE TRIGGER insert_full_name
            BEFORE INSERT ON employees
            FOR EACH ROW
            BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)),  LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER update_full_name
            BEFORE UPDATE ON employees
            FOR EACH ROW
            BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)),  LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END;
        ");

        // 3) OPTIONAL: Clean up shift_schedules (drop employee_id FK + column IF it exists)
        if (Schema::hasTable('shift_schedules')) {
            // Drop FK first if the column exists and FK is present
            try {
                if (Schema::hasColumn('shift_schedules', 'employee_id')) {
                    Schema::table('shift_schedules', function (Blueprint $table) {
                        // Drop FK if present (safe try/catch below)
                        try {
                            $table->dropForeign(['employee_id']);
                        } catch (\Throwable $e) {
                            // ignore if FK name differs / not present
                        }
                    });

                    // Now drop the column
                    Schema::table('shift_schedules', function (Blueprint $table) {
                        $table->dropColumn('employee_id');
                    });
                }
            } catch (\Throwable $e) {
                // Intentionally ignore — environment differences, prior state etc.
            }
        }

        // 4) OPTIONAL: Sync employees.shift_id from shift_schedules.shift_id (only if both exist)
        try {
            if (
                Schema::hasTable('shift_schedules') &&
                Schema::hasColumn('employees', 'shift_schedule_id') &&
                Schema::hasColumn('employees', 'shift_id') &&
                Schema::hasColumn('shift_schedules', 'id') &&
                Schema::hasColumn('shift_schedules', 'shift_id')
            ) {
                DB::statement('
                    UPDATE employees e
                    JOIN shift_schedules ss ON e.shift_schedule_id = ss.id
                    SET e.shift_id = ss.shift_id
                    WHERE e.shift_schedule_id IS NOT NULL
                ');
            }
        } catch (\Throwable $e) {
            // If this runs on a brand-new install before shift_schedules exists, just skip
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        // Drop triggers first
        DB::unprepared("DROP TRIGGER IF EXISTS insert_full_name;");
        DB::unprepared("DROP TRIGGER IF EXISTS update_full_name;");

        Schema::dropIfExists('employees');

        // We DO NOT re-add shift_schedules.employee_id here since this
        // migration aims to keep a clean one-file state.
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();
    }
};
