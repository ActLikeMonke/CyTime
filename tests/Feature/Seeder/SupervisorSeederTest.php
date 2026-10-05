<?php

namespace Tests\Feature\Seeder;

use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\SupervisorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupervisorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_seeder_uses_environment_credentials_and_is_idempotent(): void
    {
        config([
            'seeding.supervisor_email' => 'initial.supervisor@example.test',
            'seeding.supervisor_password' => 'EnvProvidedStrongPass123!',
        ]);

        $this->seed(SupervisorSeeder::class);
        $this->seed(SupervisorSeeder::class);

        $supervisor = User::where('email', 'initial.supervisor@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_SUPERVISOR, $supervisor->role);
        $this->assertTrue(Hash::check('EnvProvidedStrongPass123!', $supervisor->password));
        $this->assertSame(1, $supervisor->workSchedules()->count());
        $this->assertSame(1, WorkSchedule::count());
    }

    public function test_supervisor_seeder_requires_environment_credentials(): void
    {
        config([
            'seeding.supervisor_email' => null,
            'seeding.supervisor_password' => null,
        ]);

        $this->expectException(\RuntimeException::class);

        app(SupervisorSeeder::class)->run();
    }

    public function test_supervisor_seeder_rejects_weak_production_credentials(): void
    {
        $this->app['env'] = 'production';
        config([
            'seeding.supervisor_email' => 'initial.supervisor@example.test',
            'seeding.supervisor_password' => 'password',
        ]);

        $this->expectException(\RuntimeException::class);

        app(SupervisorSeeder::class)->run();
    }
}
