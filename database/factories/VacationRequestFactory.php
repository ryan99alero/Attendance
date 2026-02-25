<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacationRequest>
 */
class VacationRequestFactory extends Factory
{
    public function definition(): array
    {
        $startDate = Carbon::parse($this->faker->dateTimeBetween('now', '+3 months'));
        $endDate = Carbon::parse($this->faker->dateTimeBetween($startDate, $startDate->copy()->addWeek()));
        $isHalfDay = $this->faker->boolean(20);

        return [
            'employee_id' => Employee::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_half_day' => $isHalfDay,
            'hours_requested' => VacationRequest::calculateHoursRequested($startDate, $endDate, $isHalfDay),
            'notes' => $this->faker->optional()->sentence(),
            'status' => VacationRequest::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VacationRequest::STATUS_PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VacationRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VacationRequest::STATUS_DENIED,
            'reviewed_at' => now(),
            'review_notes' => $this->faker->sentence(),
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
