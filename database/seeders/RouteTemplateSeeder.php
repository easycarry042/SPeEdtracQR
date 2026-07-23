<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStep;
use Illuminate\Database\Seeder;

/**
 * Seeds the default internal-request routes. The system is a generic
 * per-department request engine (service-catalog model): each template is one
 * kind of request an office can file, with its own endorsement chain. Most
 * are non-monetary; "Procurement Request" is the one budget-driven example
 * (RA 12009: SVP below ₱2M, public bidding at ₱2M and above).
 * Requires DepartmentSeeder to have run first.
 */
class RouteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::whereIn('code', ['OM', 'BO', 'BAC', 'GSO', 'HRMO'])->get()->keyBy('code');

        if ($departments->count() < 5) {
            $this->command?->warn('RouteTemplateSeeder skipped: run DepartmentSeeder first.');

            return;
        }

        $templates = [
            [
                'name' => 'Procurement Request',
                'description' => 'Purchase request flow under RA 12009: Mayor approval, Budget certification, BAC procurement (SVP below ₱2M, public bidding at ₱2M and above), then GSO delivery and inspection.',
                'steps' => [
                    ['step_order' => 1, 'code' => 'OM', 'action' => 'Approve request', 'condition' => null],
                    ['step_order' => 2, 'code' => 'BO', 'action' => 'Certify fund availability', 'condition' => RouteTemplateStep::CONDITION_HAS_AMOUNT],
                    ['step_order' => 3, 'code' => 'BAC', 'action' => 'Small Value Procurement', 'condition' => RouteTemplateStep::CONDITION_BELOW_THRESHOLD],
                    ['step_order' => 3, 'code' => 'BAC', 'action' => 'Public Bidding', 'condition' => RouteTemplateStep::CONDITION_AT_LEAST_THRESHOLD],
                    ['step_order' => 4, 'code' => 'GSO', 'action' => 'Delivery & inspection', 'condition' => RouteTemplateStep::CONDITION_HAS_AMOUNT],
                ],
            ],
            [
                'name' => 'Job / Work Order',
                'description' => 'Repairs and maintenance (aircon, plumbing, electrical, carpentry): the General Services Office assesses and completes the work.',
                'steps' => [
                    ['step_order' => 1, 'code' => 'GSO', 'action' => 'Assess & complete the work', 'condition' => null],
                ],
            ],
            [
                'name' => 'Vehicle Request',
                'description' => 'Use of a municipal vehicle: the Mayor\'s Office approves the travel, then GSO dispatches the vehicle and issues the trip ticket.',
                'steps' => [
                    ['step_order' => 1, 'code' => 'OM', 'action' => 'Approve vehicle use', 'condition' => null],
                    ['step_order' => 2, 'code' => 'GSO', 'action' => 'Dispatch vehicle & issue trip ticket', 'condition' => null],
                ],
            ],
            [
                'name' => 'Personnel Action Request',
                'description' => 'HR matters filed on behalf of an office: leave endorsements, training requests, certifications of employment, and similar personnel actions.',
                'steps' => [
                    ['step_order' => 1, 'code' => 'HRMO', 'action' => 'Process personnel action', 'condition' => null],
                ],
            ],
        ];

        foreach ($templates as $definition) {
            $template = RouteTemplate::updateOrCreate(
                ['name' => $definition['name']],
                ['description' => $definition['description'], 'is_active' => true],
            );

            // Rebuild the chain wholesale so re-seeding stays deterministic.
            $template->steps()->delete();

            $template->steps()->createMany(array_map(fn (array $step) => [
                'step_order' => $step['step_order'],
                'department_id' => $departments[$step['code']]->id,
                'action' => $step['action'],
                'condition' => $step['condition'],
            ], $definition['steps']));
        }
    }
}
