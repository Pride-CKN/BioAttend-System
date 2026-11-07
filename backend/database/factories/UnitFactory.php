<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('UNT-###')),
            'name' => $this->faker->words(2, true) . ' Unit',
            'division_id' => Division::factory(),
            'head_id' => null,
            'is_active' => true,
        ];
    }
}
