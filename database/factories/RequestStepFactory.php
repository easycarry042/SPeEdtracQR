<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\RequestStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestStep>
 */
class RequestStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'step_order' => 1,
            'department_id' => Department::factory(),
            'action' => 'Approve',
            'status' => RequestStep::STATUS_PENDING,
        ];
    }
}
