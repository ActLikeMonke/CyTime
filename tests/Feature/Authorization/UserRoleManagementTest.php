<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_promote_an_employee_and_the_change_is_audited(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        app(UserRoleService::class)->changeRole($supervisor, $employee, User::ROLE_SUPERVISOR);

        $this->assertSame(User::ROLE_SUPERVISOR, $employee->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
            'old_values' => json_encode(['role' => User::ROLE_EMPLOYEE]),
            'new_values' => json_encode(['role' => User::ROLE_SUPERVISOR]),
        ]);
    }

    public function test_supervisor_can_demote_another_supervisor_when_one_remains(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        app(UserRoleService::class)->changeRole($supervisor, $otherSupervisor, User::ROLE_EMPLOYEE);

        $this->assertSame(User::ROLE_EMPLOYEE, $otherSupervisor->fresh()->role);
        $this->assertSame(User::ROLE_SUPERVISOR, $supervisor->fresh()->role);
    }

    public function test_the_last_supervisor_cannot_be_demoted(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        try {
            app(UserRoleService::class)->changeRole($supervisor, $supervisor, User::ROLE_EMPLOYEE);
            $this->fail('Expected last-supervisor protection to reject the demotion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $this->assertSame(User::ROLE_SUPERVISOR, $supervisor->fresh()->role);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_employee_cannot_change_roles(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $target = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $this->expectException(AuthorizationException::class);

        app(UserRoleService::class)->changeRole($employee, $target, User::ROLE_SUPERVISOR);
    }

    public function test_supervisor_employee_management_page_is_role_protected(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($employee)->get(route('supervisor.users.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('supervisor.users.index'))->assertOk();
    }

    public function test_supervisor_can_promote_an_employee_from_the_management_page(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.users')
            ->call('promote', $employee->id)
            ->assertHasNoErrors();

        $this->assertSame(User::ROLE_SUPERVISOR, $employee->fresh()->role);
    }

    public function test_employee_cannot_open_supervisor_employee_management_component(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        Livewire::actingAs($employee)
            ->test('pages::supervisor.users')
            ->assertForbidden();
    }
}
