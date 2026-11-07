<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('DIV-###')),
            'name' => $this->faker->unique()->randomElement([
                'Human Resources & Development', 
                'Engineering & Technology', 
                'Finance & Accounting', 
                'Operations & Logistics'
            ]),
            'head_id' => null,
            'is_active' => true,
        ];
    }
}
