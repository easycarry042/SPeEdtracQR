<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteTemplateStep>
 */
class RouteTemplateStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_template_id' => RouteTemplate::factory(),
            'step_order' => 1,
            'department_id' => Department::factory(),
            'action' => 'Approve',
            'condition' => null,
        ];
    }
}
