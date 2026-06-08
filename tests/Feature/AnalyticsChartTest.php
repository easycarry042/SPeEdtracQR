<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_chart_data_returns_json_for_super_admin(): void
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($user)->getJson(route('analytics.data', [
            'from' => $from,
            'to' => $to,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['labels', 'submitted', 'completed', 'scoped']);
    }
}
