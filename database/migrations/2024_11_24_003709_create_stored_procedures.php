<?php
//
//use Illuminate\Database\Migrations\Migration;
//use Illuminate\Database\Schema\Blueprint;
//use Illuminate\Support\Facades\Schema;
//use Illuminate\Support\Facades\DB;
//
//return new class extends Migration
//{
//    /**
//     * Run the migrations.
//     */
//    public function up(): void
//    {
//        // Foreign keys for Attendances
//        Schema::table('attendances', function (Blueprint $table) {
//            $this->addForeignKeyIfNotExists('attendances', 'created_by', 'fk_attendance_created_by', 'users', 'id', 'set null', 'restrict');
//            $this->addForeignKeyIfNotExists('attendances', 'device_id', 'fk_attendance_device_id', 'devices', 'id', 'set null', 'restrict');
//            $this->addForeignKeyIfNotExists('attendances', 'employee_id', 'fk_attendance_employee_id', 'employees', 'id', 'cascade', 'restrict');
//            $this->addForeignKeyIfNotExists('attendances', 'updated_by', 'fk_attendance_updated_by', 'users', 'id', 'set null', 'restrict');
//        });
//
//        // Foreign keys for Cards
//        Schema::table('cards', function (Blueprint $table) {
//            $this->addForeignKeyIfNotExists('cards', 'created_by', 'fk_card_created_by', 'users', 'id', 'set null', 'restrict');
//            $this->addForeignKeyIfNotExists('cards', 'employee_id', 'fk_card_employee_id', 'employees', 'id', 'cascade', 'restrict');
//            $this->addForeignKeyIfNotExists('cards', 'updated_by', 'fk_card_updated_by', 'users', 'id', 'set null', 'restrict');
//        });
//
//        // Foreign keys for Departments
//        Schema::table('departments', function (Blueprint $table) {
//            $this->addForeignKeyIfNotExists('departments', 'created_by', 'fk_department_created_by', 'users', 'id', 'set null', 'restrict');
//            $this->addForeignKeyIfNotExists('departments', 'manager_id', 'fk_department_manager_id', 'employees', 'id', 'set null', 'restrict');
//            $this->addForeignKeyIfNotExists('departments', 'updated_by', 'fk_department_updated_by', 'users', 'id', 'set null', 'restrict');
//        });
//
//        // Add additional foreign keys for other tables as needed
//    }
//
//    /**
//     * Reverse the migrations.
//     */
//    public function down(): void
//    {
//        // Drop foreign keys for Attendances
//        Schema::table('attendances', function (Blueprint $table) {
//            $table->dropForeign('fk_attendance_created_by');
//            $table->dropForeign('fk_attendance_device_id');
//            $table->dropForeign('fk_attendance_employee_id');
//            $table->dropForeign('fk_attendance_updated_by');
//        });
//
//        // Drop foreign keys for Cards
//        Schema::table('cards', function (Blueprint $table) {
//            $table->dropForeign('fk_card_created_by');
//            $table->dropForeign('fk_card_employee_id');
//            $table->dropForeign('fk_card_updated_by');
//        });
//
//        // Drop foreign keys for Departments
//        Schema::table('departments', function (Blueprint $table) {
//            $table->dropForeign('fk_department_created_by');
//            $table->dropForeign('fk_department_manager_id');
//            $table->dropForeign('fk_department_updated_by');
//        });
//
//        // Continue dropping foreign keys for other tables
//    }
//
//    /**
//     * Helper function to add a foreign key if it does not already exist.
//     */
//    private function addForeignKeyIfNotExists(
//        string $table,
//        string $column,
//        string $keyName,
//        string $referencedTable,
//        string $referencedColumn,
//        string $onDelete = 'cascade',
//        string $onUpdate = 'restrict'
//    ): void {
//        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
//            ->where('TABLE_NAME', $table)
//            ->where('CONSTRAINT_NAME', $keyName)
//            ->exists();
//
//        if (!$exists) {
//            Schema::table($table, function (Blueprint $table) use ($column, $keyName, $referencedTable, $referencedColumn, $onDelete, $onUpdate) {
//                $table->foreign($column, $keyName)
//                    ->references($referencedColumn)
//                    ->on($referencedTable)
//                    ->onDelete($onDelete)
//                    ->onUpdate($onUpdate);
//            });
//        }
//    }
//};
