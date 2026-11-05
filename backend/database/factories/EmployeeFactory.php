<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\Division;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'nip' => 'NIP-' . $this->faker->unique()->numerify('#####'),
            'device_user_identifier' => 'DEV-' . $this->faker->unique()->numerify('###'),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'join_date' => $this->faker->dateTimeBetween('-3 years', 'now'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'unit_id' => Unit::factory(),
            'division_id' => Division::factory(),
            'position_id' => Position::factory(),
            'manager_id' => null,
            'photo_path' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => Employee::STATUS_INACTIVE,
        ]);
    }

    public function resigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => Employee::STATUS_RESIGNED,
        ]);
    }
}
