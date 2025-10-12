<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Page Description -->
        <div class="bg-primary-50 dark:bg-primary-900/20 p-4 rounded-lg border border-primary-200 dark:border-primary-700">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-lg font-medium text-primary-900 dark:text-primary-100">Custom Report Builder</h3>
                    <p class="text-sm text-primary-700 dark:text-primary-300 mt-1">
                        Create custom reports with field mapping, static values, and calculated fields.
                        Perfect for creating payroll exports with specific column names and formats.
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <div class="filament-form-component">
            {{ $this->form }}
        </div>

        <!-- Quick Tips -->
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Quick Tips</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div>
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Field Mapping</h5>
                    <p>Rename database fields to match your payroll system's requirements.</p>
                </div>
                <div>
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Static Fields</h5>
                    <p>Add columns with fixed values like company codes or default pay types.</p>
                </div>
                <div>
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Calculated Fields</h5>
                    <p>Create computed fields like gross pay calculations or formatted names.</p>
                </div>
                <div>
                    <h5 class="font-medium text-gray-800 dark:text-gray-200">Export Formats</h5>
                    <p>Generate reports in CSV, Excel, or JSON formats for different systems.</p>
                </div>
            </div>
        </div>

        <!-- Example Configurations -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
            <h4 class="text-md font-medium text-yellow-800 dark:text-yellow-200 mb-3">Common Use Cases</h4>
            <div class="space-y-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p><strong>ADP Export:</strong> Map employee codes, add company ID as static field, include gross pay calculations.</p>
                <p><strong>QuickBooks Export:</strong> Format names as "Last, First", add GL account codes, calculate total hours.</p>
                <p><strong>Custom Payroll:</strong> Include department codes, overtime multipliers, and custom pay classifications.</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>