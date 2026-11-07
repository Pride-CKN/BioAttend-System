<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('POS-###')),
            'name' => $this->faker->randomElement([
                'Manager', 
                'Supervisor', 
                'Senior Staff', 
                'Junior Staff', 
                'Specialist'
            ]),
            'level' => $this->faker->numberBetween(1, 4),
            'is_active' => true,
        ];
    }
}
