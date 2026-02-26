<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => 'ESP32_'.strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'device_name' => $this->faker->randomElement([
                'Front Lobby',
                'Back Door',
                'Break Room',
                'Main Entrance',
                'Conference Room',
            ]).' Clock',
            'display_name' => 'TimeClock ('.$this->faker->regexify('[A-F0-9]{5}').')',
            'mac_address' => $this->faker->macAddress(),
            'ip_address' => $this->faker->localIpv4(),
            'device_type' => 'esp32_timeclock',
            'firmware_version' => '1.0.0',
            'is_active' => true,
            'registration_status' => 'pending',
            'last_seen_at' => now(),
            'timezone' => 'America/Chicago',
        ];
    }

    /**
     * Indicate that the device is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_status' => 'approved',
        ]);
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the device is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_status' => 'pending',
        ]);
    }

    /**
     * Indicate that the device has been offline for a while.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subHours(2),
            'offline_alerted_at' => now()->subHour(),
        ]);
    }
}
