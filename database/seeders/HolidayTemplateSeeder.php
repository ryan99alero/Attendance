<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HolidayTemplate;

class HolidayTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            [
                'name' => "New Year's Day",
                'type' => 'fixed_date',
                'calculation_rule' => [
                    'month' => 1,
                    'day' => 1,
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => 'January 1st - New Year\'s Day',
            ],
            [
                'name' => 'Independence Day',
                'type' => 'fixed_date',
                'calculation_rule' => [
                    'month' => 7,
                    'day' => 4,
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => 'July 4th - Independence Day',
            ],
            [
                'name' => 'Christmas Day',
                'type' => 'fixed_date',
                'calculation_rule' => [
                    'month' => 12,
                    'day' => 25,
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => 'December 25th - Christmas Day',
            ],
            [
                'name' => 'Martin Luther King Jr. Day',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 1,
                    'day_of_week' => 1, // Monday
                    'occurrence' => 3, // 3rd Monday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => '3rd Monday in January',
            ],
            [
                'name' => "Presidents' Day",
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 2,
                    'day_of_week' => 1, // Monday
                    'occurrence' => 3, // 3rd Monday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => '3rd Monday in February',
            ],
            [
                'name' => 'Memorial Day',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 5,
                    'day_of_week' => 1, // Monday
                    'occurrence' => -1, // Last Monday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => 'Last Monday in May',
            ],
            [
                'name' => 'Labor Day',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 9,
                    'day_of_week' => 1, // Monday
                    'occurrence' => 1, // 1st Monday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => '1st Monday in September',
            ],
            [
                'name' => 'Columbus Day',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 10,
                    'day_of_week' => 1, // Monday
                    'occurrence' => 2, // 2nd Monday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => false, // Disabled by default as not all companies observe
                'description' => '2nd Monday in October',
            ],
            [
                'name' => 'Thanksgiving Day',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 11,
                    'day_of_week' => 4, // Thursday
                    'occurrence' => 4, // 4th Thursday
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => true,
                'description' => '4th Thursday in November',
            ],
            [
                'name' => 'Black Friday',
                'type' => 'relative',
                'calculation_rule' => [
                    'month' => 11,
                    'day_of_week' => 5, // Friday
                    'occurrence' => 4, // 4th Friday (day after Thanksgiving)
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => false, // Disabled by default
                'description' => 'Day after Thanksgiving',
            ],
            [
                'name' => 'Good Friday',
                'type' => 'custom',
                'calculation_rule' => [
                    'base' => 'easter',
                    'offset_days' => -2, // 2 days before Easter
                ],
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'is_active' => false, // Disabled by default
                'description' => 'Friday before Easter Sunday',
            ],
        ];

        foreach ($holidays as $holiday) {
            HolidayTemplate::updateOrCreate(
                ['name' => $holiday['name']],
                $holiday
            );
        }
    }
}