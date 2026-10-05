<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DataRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_expired_deactivated_account_is_anonymized_without_deleting_official_records(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 07:00:00');
        $user = User::factory()->create([
            'first_name' => 'Taylor',
            'last_name' => 'Example',
            'email' => 'taylor@example.test',
            'birth_date' => '1990-03-12',
        ]);
        $user->forceFill(['deactivated_at' => '2024-10-04 12:00:00'])->save();
        $entryId = DB::table('time_entries')->insertGetId([
            'user_id' => $user->id,
            'date' => '2024-01-01',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 30,
            'status' => 'final',
            'is_school_day' => false,
            'created_at' => '2024-01-01 17:00:00',
            'updated_at' => '2024-01-01 17:00:00',
        ]);
        $auditLogId = DB::table('audit_logs')->insertGetId([
            'user_id' => $user->id,
            'auditable_type' => 'App\\Models\\TimeEntry',
            'auditable_id' => $entryId,
            'old_values' => null,
            'new_values' => json_encode(['start_time' => '09:00'], JSON_THROW_ON_ERROR),
            'created_at' => '2024-01-01 17:00:00',
        ]);

        $this->artisan('data-retention:anonymize-expired-accounts')
            ->expectsOutput('Anonymized 1 expired accounts.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('Former', $user->first_name);
        $this->assertSame('Employee', $user->last_name);
        $this->assertSame("former-user-{$user->id}@invalid.cytime", $user->email);
        $this->assertNull($user->birth_date);
        $this->assertNotNull($user->personal_data_anonymized_at);
        $this->assertDatabaseHas('time_entries', ['id' => $entryId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $auditLogId, 'user_id' => $user->id]);

        $this->artisan('data-retention:anonymize-expired-accounts')
            ->expectsOutput('Anonymized 0 expired accounts.')
            ->assertSuccessful();
    }

    public function test_active_or_not_yet_expired_accounts_are_not_anonymized(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 07:00:00');
        $active = User::factory()->create(['email' => 'active@example.test']);
        $recent = User::factory()->create(['email' => 'recent@example.test']);
        $recent->forceFill(['deactivated_at' => '2025-10-05 07:00:00'])->save();

        $this->artisan('data-retention:anonymize-expired-accounts')
            ->expectsOutput('Anonymized 0 expired accounts.')
            ->assertSuccessful();

        $this->assertSame('active@example.test', $active->fresh()->email);
        $this->assertSame('recent@example.test', $recent->fresh()->email);
    }

    public function test_anonymization_retention_cannot_be_configured_below_two_years(): void
    {
        config(['data_retention.account_anonymization_years' => 1]);

        $this->expectException(InvalidArgumentException::class);
        app(DataRetentionService::class)->anonymizeExpiredAccounts();
    }
}
