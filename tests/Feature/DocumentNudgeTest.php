<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentNudgeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    public function test_super_admin_notifies_the_assignee_of_an_overdue_document(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $assignee = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-NUDGE-1',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'assigned_to' => $assignee->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.nudge', $document), ['note' => 'Please prioritise this.'])
            ->assertRedirect();

        Notification::assertSentTo($assignee, DocumentEvent::class);
    }

    public function test_an_unassigned_document_notifies_the_supervisors(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $document = Document::create([
            'tracking_number' => 'SPD-NUDGE-2',
            'document_type' => 'Cedula',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.nudge', $document))
            ->assertRedirect();

        Notification::assertSentTo($supervisor, DocumentEvent::class);
    }

    public function test_staff_cannot_nudge(): void
    {
        Notification::fake();

        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-NUDGE-3',
            'document_type' => 'Cedula',
            'status' => 'pending',
        ]);

        // Bounced to their own dashboard by the manage-system permission gate.
        $this->actingAs($staff)
            ->post(route('admin.nudge', $document))
            ->assertRedirect();

        Notification::assertNothingSent();
    }
}
