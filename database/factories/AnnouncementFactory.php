<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audio_type' => Announcement::AUDIO_NONE,
            'target_type' => Announcement::TARGET_ALL,
            'department_id' => null,
            'employee_id' => null,
            'priority' => Announcement::PRIORITY_NORMAL,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
            'require_acknowledgment' => false,
            'created_by' => null,
        ];
    }

    /**
     * Target a specific department.
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => Announcement::TARGET_DEPARTMENT,
            'department_id' => $department->id,
        ]);
    }

    /**
     * Target a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => Announcement::TARGET_EMPLOYEE,
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set high priority with buzz alert.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Announcement::PRIORITY_URGENT,
            'audio_type' => Announcement::AUDIO_BUZZ,
            'require_acknowledgment' => true,
        ]);
    }

    /**
     * Set announcement to be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set announcement to expire in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set announcement to start in the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
        ]);
    }
}
