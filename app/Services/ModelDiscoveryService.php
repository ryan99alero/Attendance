<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ModelDiscoveryService
{
    protected array $modelCache = [];
    protected array $fieldCache = [];

    /**
     * Get all available models with their display names
     */
    public function getAvailableModels(): array
    {
        if (!empty($this->modelCache)) {
            return $this->modelCache;
        }

        $models = [
            'employees' => [
                'class' => \App\Models\Employee::class,
                'label' => 'Employees',
                'table' => 'employees'
            ],
            'attendances' => [
                'class' => \App\Models\Attendance::class,
                'label' => 'Attendances',
                'table' => 'attendances'
            ],
            'departments' => [
                'class' => \App\Models\Department::class,
                'label' => 'Departments',
                'table' => 'departments'
            ],
            'punches' => [
                'class' => \App\Models\Punch::class,
                'label' => 'Punches',
                'table' => 'punches'
            ],
            'devices' => [
                'class' => \App\Models\Device::class,
                'label' => 'Devices',
                'table' => 'devices'
            ],
            'shifts' => [
                'class' => \App\Models\Shift::class,
                'label' => 'Shifts',
                'table' => 'shifts'
            ],
            'shift_schedules' => [
                'class' => \App\Models\ShiftSchedule::class,
                'label' => 'Shift Schedules',
                'table' => 'shift_schedules'
            ],
            'classifications' => [
                'class' => \App\Models\Classification::class,
                'label' => 'Classifications',
                'table' => 'classifications'
            ],
            'punch_types' => [
                'class' => \App\Models\PunchType::class,
                'label' => 'Punch Types',
                'table' => 'punch_types'
            ],
            'vacation_policies' => [
                'class' => \App\Models\VacationPolicy::class,
                'label' => 'Vacation Policies',
                'table' => 'vacation_policies'
            ],
            'holidays' => [
                'class' => \App\Models\Holiday::class,
                'label' => 'Holidays',
                'table' => 'holidays'
            ],
            'pay_periods' => [
                'class' => \App\Models\PayPeriod::class,
                'label' => 'Pay Periods',
                'table' => 'pay_periods'
            ],
            'users' => [
                'class' => \App\Models\User::class,
                'label' => 'Users',
                'table' => 'users'
            ],
        ];

        // Filter to only include models that actually exist
        $this->modelCache = array_filter($models, function($model) {
            return class_exists($model['class']);
        });

        return $this->modelCache;
    }

    /**
     * Get fields for a specific model
     */
    public function getModelFields(string $modelKey): array
    {
        $cacheKey = "fields_$modelKey";

        if (isset($this->fieldCache[$cacheKey])) {
            return $this->fieldCache[$cacheKey];
        }

        $models = $this->getAvailableModels();

        if (!isset($models[$modelKey])) {
            return [];
        }

        $modelInfo = $models[$modelKey];
        $tableName = $modelInfo['table'];

        // Get table columns
        $columns = Schema::getColumnListing($tableName);

        $fields = [];

        foreach ($columns as $column) {
            // Skip some common fields that aren't useful for reports
            if (in_array($column, ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) {
                continue;
            }

            $label = $this->generateFieldLabel($column);
            $fields[$column] = $label;
        }

        // Add computed/relationship fields
        $relationshipFields = $this->getRelationshipFields($modelInfo['class']);
        $fields = array_merge($fields, $relationshipFields);

        $this->fieldCache[$cacheKey] = $fields;

        return $fields;
    }

    /**
     * Generate a human-readable label for a field
     */
    protected function generateFieldLabel(string $fieldName): string
    {
        // Convert snake_case to Title Case
        $label = Str::title(str_replace(['_', '-'], ' ', $fieldName));

        // Handle some common abbreviations
        $replacements = [
            'Id' => 'ID',
            'Ip' => 'IP',
            'Mac' => 'MAC',
            'Ntp' => 'NTP',
            'Api' => 'API',
            'Url' => 'URL',
            'Ot' => 'OT',
            'Hr' => 'HR',
        ];

        foreach ($replacements as $search => $replace) {
            $label = str_replace($search, $replace, $label);
        }

        return $label;
    }

    /**
     * Get relationship fields for a model
     */
    protected function getRelationshipFields(string $modelClass): array
    {
        $fields = [];

        try {
            $reflection = new ReflectionClass($modelClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $methodName = $method->getName();

                // Skip getters, setters, and common methods
                if (Str::startsWith($methodName, ['get', 'set', 'is', 'has', 'scope']) ||
                    in_array($methodName, ['toArray', 'toJson', 'save', 'delete', 'update', 'create'])) {
                    continue;
                }

                // Check if method might be a relationship
                if ($method->getNumberOfParameters() === 0) {
                    // Add as computed field
                    $label = $this->generateFieldLabel($methodName) . ' (Related)';
                    $fields[$methodName] = $label;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors in reflection
        }

        return $fields;
    }

    /**
     * Get field options for Filament Select component
     */
    public function getModelOptionsForSelect(): array
    {
        $models = $this->getAvailableModels();
        $options = [];

        foreach ($models as $key => $model) {
            $options[$key] = $model['label'];
        }

        return $options;
    }

    /**
     * Get field options for a specific model for Filament Select component
     */
    public function getFieldOptionsForSelect(string $modelKey): array
    {
        return $this->getModelFields($modelKey);
    }

    /**
     * Clear caches
     */
    public function clearCache(): void
    {
        $this->modelCache = [];
        $this->fieldCache = [];
    }
}