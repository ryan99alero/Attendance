<?php

namespace Database\Factories;

use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifier = strtoupper($this->faker->regexify('[A-F0-9]{8}'));

        return [
            'kind' => $this->faker->randomElement(['rfid', 'nfc', 'mifare']),
            'identifier' => $identifier,
            'identifier_hash' => hash('sha256', $identifier),
            'is_active' => true,
            'issued_at' => now(),
        ];
    }

    /**
     * Indicate that the credential is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Indicate that the credential is an RFID type.
     */
    public function rfid(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'rfid',
        ]);
    }

    /**
     * Indicate that the credential is an NFC type.
     */
    public function nfc(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'nfc',
        ]);
    }
}
