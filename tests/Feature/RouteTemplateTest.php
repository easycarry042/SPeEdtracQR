<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStep;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RouteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_template_with_branching_steps(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        [$mayor, $bac] = Department::factory()->count(2)->create();

        $this->actingAs($admin)->post(route('admin.route-templates.store'), [
            'name' => 'Procurement Request',
            'description' => 'RA 12009 flow',
            'steps' => [
                ['step_order' => 1, 'department_id' => $mayor->id, 'action' => 'Approve request', 'condition' => null],
                ['step_order' => 2, 'department_id' => $bac->id, 'action' => 'Small Value Procurement', 'condition' => RouteTemplateStep::CONDITION_BELOW_THRESHOLD],
                ['step_order' => 2, 'department_id' => $bac->id, 'action' => 'Public Bidding', 'condition' => RouteTemplateStep::CONDITION_AT_LEAST_THRESHOLD],
            ],
        ])->assertRedirect(route('admin.route-templates.index'));

        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();
        $this->assertCount(3, $template->steps);
    }

    public function test_template_requires_at_least_one_valid_step(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)->post(route('admin.route-templates.store'), [
            'name' => 'Empty Route',
            'steps' => [],
        ])->assertSessionHasErrors('steps');

        $this->actingAs($admin)->post(route('admin.route-templates.store'), [
            'name' => 'Bad Dept Route',
            'steps' => [
                ['step_order' => 1, 'department_id' => 999999, 'action' => 'Approve', 'condition' => null],
            ],
        ])->assertSessionHasErrors('steps.0.department_id');
    }

    public function test_updating_a_template_replaces_its_steps(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $template = RouteTemplate::factory()->create();
        $oldStep = RouteTemplateStep::factory()->create(['route_template_id' => $template->id]);
        $newDept = Department::factory()->create();

        $this->actingAs($admin)->put(route('admin.route-templates.update', $template), [
            'name' => $template->name,
            'steps' => [
                ['step_order' => 1, 'department_id' => $newDept->id, 'action' => 'Certify fund availability', 'condition' => RouteTemplateStep::CONDITION_HAS_AMOUNT],
            ],
        ])->assertRedirect(route('admin.route-templates.index'));

        $steps = $template->fresh()->steps;
        $this->assertCount(1, $steps);
        $this->assertSame('Certify fund availability', $steps->first()->action);
        $this->assertDatabaseMissing('route_template_steps', ['id' => $oldStep->id]);
    }

    public function test_supervisor_cannot_manage_route_templates(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        // Unauthorized admin-area hits bounce Supervisors back to their dashboard.
        $this->actingAs($supervisor)->get(route('admin.route-templates.index'))->assertForbidden();
    }

    public function test_steps_resolve_by_amount_around_the_bidding_threshold(): void
    {
        $template = RouteTemplate::factory()->create();
        $template->steps()->createMany([
            ['step_order' => 1, 'department_id' => Department::factory()->create()->id, 'action' => 'Approve request', 'condition' => null],
            ['step_order' => 2, 'department_id' => Department::factory()->create()->id, 'action' => 'Certify fund availability', 'condition' => RouteTemplateStep::CONDITION_HAS_AMOUNT],
            ['step_order' => 3, 'department_id' => Department::factory()->create()->id, 'action' => 'Small Value Procurement', 'condition' => RouteTemplateStep::CONDITION_BELOW_THRESHOLD],
            ['step_order' => 3, 'department_id' => Department::factory()->create()->id, 'action' => 'Public Bidding', 'condition' => RouteTemplateStep::CONDITION_AT_LEAST_THRESHOLD],
        ]);
        $template->load('steps');

        // No amount: only the unconditional approval hop applies.
        $this->assertSame(['Approve request'], $template->stepsForAmount(null)->pluck('action')->all());

        // Below ₱2M: budget certification + SVP path.
        $this->assertSame(
            ['Approve request', 'Certify fund availability', 'Small Value Procurement'],
            $template->stepsForAmount(1_500_000)->pluck('action')->all(),
        );

        // At the ₱2M line and above: public bidding path.
        $this->assertSame(
            ['Approve request', 'Certify fund availability', 'Public Bidding'],
            $template->stepsForAmount(RouteTemplate::BIDDING_THRESHOLD)->pluck('action')->all(),
        );
    }

    public function test_seeded_procurement_template_resolves_a_fixed_chain(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        // The seeded chain no longer branches on amount — the peso figure lives
        // on the scanned paper, not in the routing logic.
        $this->assertSame(
            ['Approve request', 'Certify fund availability', 'Procurement', 'Delivery & inspection'],
            $template->stepsForAmount(null)->pluck('action')->all(),
        );
    }

    public function test_seeder_ships_non_monetary_templates_whose_chains_ignore_the_amount(): void
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        $this->assertSame(
            ['Job / Work Order', 'Personnel Action Request', 'Procurement Request', 'Vehicle Request'],
            RouteTemplate::orderBy('name')->pluck('name')->all(),
        );

        // Unconditional chains resolve identically with or without an amount.
        $vehicle = RouteTemplate::where('name', 'Vehicle Request')->firstOrFail();
        $expected = ['Approve vehicle use', 'Dispatch vehicle & issue trip ticket'];
        $this->assertSame($expected, $vehicle->stepsForAmount(null)->pluck('action')->all());
        $this->assertSame($expected, $vehicle->stepsForAmount(5_000_000)->pluck('action')->all());
    }

    public function test_internal_documents_carry_an_endorsement_chain(): void
    {
        $requesting = Department::factory()->create();
        $mayor = Department::factory()->create();

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-INT001',
            'document_type' => 'Procurement Request',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $requesting->id,
            'amount' => 250_000,
        ]);

        $document->requestSteps()->createMany([
            ['step_order' => 1, 'department_id' => $mayor->id, 'action' => 'Approve request', 'status' => RequestStep::STATUS_CURRENT],
            ['step_order' => 2, 'department_id' => $requesting->id, 'action' => 'Receive items', 'status' => RequestStep::STATUS_PENDING],
        ]);

        $this->assertTrue($document->isInternal());
        $this->assertTrue($document->requestingDepartment->is($requesting));
        $this->assertSame('Approve request', $document->currentRequestStep()?->action);

        // External tickets stay department-free by default.
        $external = Document::create([
            'tracking_number' => 'SPD-TEST-EXT001',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);
        $this->assertFalse($external->isInternal());
        $this->assertNull($external->currentRequestStep());
    }
}
