<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_supervisors_can_list_users(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($supervisor)->allows('viewAny', User::class));
    }

    public function test_employees_can_only_view_their_own_user_record(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $otherEmployee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->assertTrue(Gate::forUser($employee)->allows('view', $employee));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $otherEmployee));
        $this->assertTrue(Gate::forUser($supervisor)->allows('view', $employee));
    }

    public function test_role_middleware_restricts_supervisor_routes(): void
    {
        Route::get('/test/supervisor-only', fn () => response()->noContent())
            ->middleware(['auth', 'role:supervisor']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_EMPLOYEE]))
            ->get('/test/supervisor-only')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERVISOR]))
            ->get('/test/supervisor-only')
            ->assertNoContent();
    }

    public function test_unknown_roles_do_not_receive_employee_or_supervisor_permissions(): void
    {
        $user = User::factory()->create(['role' => 'unknown']);
        $target = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $this->assertFalse($user->isEmployee());
        $this->assertFalse($user->isSupervisor());
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($user)->allows('view', $target));
    }
}
