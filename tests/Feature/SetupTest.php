<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeds_a_supervisor_employees_and_work_schedules(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => config('seeding.supervisor_email'),
            'role' => 'supervisor',
        ]);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('work_schedules', 4);
        $this->assertDatabaseHas('users', [
            'email' => 'sam.parttime@cytime.test',
            'annual_leave_days' => 18,
        ]);
    }

    public function test_time_entries_and_absences_have_soft_delete_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('time_entries', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('absences', 'deleted_at'));
    }
}
