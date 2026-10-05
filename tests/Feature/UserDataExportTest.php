<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_export_contains_the_users_records_and_excludes_credentials_and_other_users(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'last_name' => 'Employee',
            'email' => 'alex@example.test',
        ]);
        $other = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'email' => 'other@example.test',
        ]);
        $entryId = DB::table('time_entries')->insertGetId([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 30,
            'status' => 'final',
            'is_school_day' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('time_entries')->insert([
            'user_id' => $other->id,
            'date' => '2026-10-01',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 30,
            'status' => 'final',
            'is_school_day' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $other->id,
            'auditable_type' => 'App\\Models\\TimeEntry',
            'auditable_id' => $entryId,
            'old_values' => null,
            'new_values' => json_encode(['start_time' => '09:00:00'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('settings.data-export', ['format' => 'json']))
            ->assertOk()
            ->assertDownload('cytime-data-export-'.now()->format('Ymd').'.json');
        $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('alex@example.test', $data['profile']['email']);
        $this->assertCount(1, $data['time_entries']);
        $this->assertSame($entryId, $data['time_entries'][0]['id']);
        $this->assertCount(1, $data['audit_history']);
        $this->assertArrayNotHasKey('password', $data['profile']);
        $this->assertArrayNotHasKey('two_factor_secret', $data['profile']);
        $this->assertStringNotContainsString('other@example.test', $response->streamedContent());
    }

    public function test_csv_export_uses_a_consistent_machine_readable_shape_and_is_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('settings.data-export', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload('cytime-data-export-'.now()->format('Ymd').'.csv');

        $this->assertStringStartsWith('record_type,record_id,data_json', $response->streamedContent());
        $this->assertStringContainsString('profile', $response->streamedContent());
        $this->assertStringContainsString($user->email, $response->streamedContent());

        auth()->logout();
        $this->get(route('settings.data-export', ['format' => 'json']))
            ->assertRedirect(route('login'));
    }
}
