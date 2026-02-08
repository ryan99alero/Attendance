<?php

namespace Database\Seeders;

use App\Models\IntegrationConnection;
use App\Models\IntegrationFieldMapping;
use App\Models\IntegrationObject;
use Illuminate\Database\Seeder;

class EmployeeIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        // Find the first active Pace connection
        $connection = IntegrationConnection::where('driver', 'pace')
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            $this->command->warn('No active Pace connection found. Skipping Employee integration seeder.');
            return;
        }

        // Create or update the Employee IntegrationObject
        $object = IntegrationObject::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'object_name' => 'Employee',
            ],
            [
                'display_name' => 'Employee',
                'description' => 'Pace ERP employees synced to the local employees table',
                'primary_key_field' => '@id',
                'primary_key_type' => 'Integer',
                'local_model' => 'App\\Models\\Employee',
                'local_table' => 'employees',
                'default_filter' => "@status = 'A'",
                'sync_enabled' => true,
                'sync_direction' => 'pull',
                'api_method' => 'loadValueObjects',
                'sync_frequency' => 'manual',
            ]
        );

        // Define all field mappings
        $fieldMappings = [
            [
                'external_field' => 'external_id',
                'external_xpath' => '@id',
                'external_type' => 'Integer',
                'local_field' => 'external_id',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => true,
                'local_table' => null,
            ],
            [
                'external_field' => 'first_name',
                'external_xpath' => '@firstName',
                'external_type' => 'String',
                'local_field' => 'first_name',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'last_name',
                'external_xpath' => '@lastName',
                'external_type' => 'String',
                'local_field' => 'last_name',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'email',
                'external_xpath' => '@email',
                'external_type' => 'String',
                'local_field' => 'email',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'phone',
                'external_xpath' => '@phoneNumber',
                'external_type' => 'String',
                'local_field' => 'phone',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'address',
                'external_xpath' => '@address1',
                'external_type' => 'String',
                'local_field' => 'address',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'address2',
                'external_xpath' => '@address2',
                'external_type' => 'String',
                'local_field' => 'address2',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'city',
                'external_xpath' => '@city',
                'external_type' => 'String',
                'local_field' => 'city',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'state',
                'external_xpath' => '@state',
                'external_type' => 'String',
                'local_field' => 'state',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'zip',
                'external_xpath' => '@zip',
                'external_type' => 'String',
                'local_field' => 'zip',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'country',
                'external_xpath' => '/country/@isoCountry',
                'external_type' => 'String',
                'local_field' => 'country',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'department_code',
                'external_xpath' => '@department',
                'external_type' => 'String',
                'local_field' => 'department_id',
                'local_type' => 'integer',
                'transform' => 'fk_lookup',
                'transform_options' => [
                    'model' => 'App\\Models\\Department',
                    'match_column' => 'external_department_id',
                    'return_column' => 'id',
                ],
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'pay_rate',
                'external_xpath' => '@payRate01',
                'external_type' => 'Currency',
                'local_field' => 'pay_rate',
                'local_type' => 'float',
                'transform' => 'string_to_float',
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'date_of_hire',
                'external_xpath' => '@startDate',
                'external_type' => 'Date',
                'local_field' => 'date_of_hire',
                'local_type' => 'date',
                'transform' => 'date_ms_to_carbon',
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'termination_date',
                'external_xpath' => '@terminationDate',
                'external_type' => 'Date',
                'local_field' => 'termination_date',
                'local_type' => 'date',
                'transform' => 'date_ms_to_carbon',
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'birth_date',
                'external_xpath' => '@birthDate',
                'external_type' => 'Date',
                'local_field' => 'birth_date',
                'local_type' => 'date',
                'transform' => 'date_ms_to_carbon',
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'emergency_contact',
                'external_xpath' => '@emergencyContact',
                'external_type' => 'String',
                'local_field' => 'emergency_contact',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'emergency_phone',
                'external_xpath' => '@emergencyPhone',
                'external_type' => 'String',
                'local_field' => 'emergency_phone',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'notes',
                'external_xpath' => '@notes',
                'external_type' => 'String',
                'local_field' => 'notes',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'default_shift',
                'external_xpath' => '@defaultShift',
                'external_type' => 'Integer',
                'local_field' => 'shift_id',
                'local_type' => 'integer',
                'transform' => 'string_to_int',
                'transform_options' => null,
                'sync_on_pull' => true,
                'is_identifier' => false,
                'local_table' => null,
            ],
            // Fetch-only fields (no local_field, sync_on_pull = false)
            [
                'external_field' => 'is_active',
                'external_xpath' => '@status',
                'external_type' => 'String',
                'local_field' => '',
                'local_type' => 'string',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => false,
                'is_identifier' => false,
                'local_table' => null,
            ],
            [
                'external_field' => 'is_supervisor',
                'external_xpath' => '@isSupervisor',
                'external_type' => 'Boolean',
                'local_field' => '',
                'local_type' => 'boolean',
                'transform' => null,
                'transform_options' => null,
                'sync_on_pull' => false,
                'is_identifier' => false,
                'local_table' => null,
            ],
        ];

        foreach ($fieldMappings as $mapping) {
            IntegrationFieldMapping::updateOrCreate(
                [
                    'object_id' => $object->id,
                    'external_field' => $mapping['external_field'],
                ],
                array_merge($mapping, ['object_id' => $object->id])
            );
        }

        $this->command->info("Employee integration seeded: {$object->id} with " . count($fieldMappings) . " field mappings.");
    }
}
