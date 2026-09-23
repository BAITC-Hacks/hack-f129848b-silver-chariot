<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatasetImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login_without_exposing_navigation_or_profiles(): void
    {
        $this->get('/employees')->assertRedirectToRoute('login');
        $this->get('/login')->assertSeeText('Войти')->assertDontSee('name="role"', false)
            ->assertDontSee(route('hr.index'))->assertDontSee(route('admin.upload'));
    }

    #[TestWith(['GET', '/employees'])]
    #[TestWith(['GET', '/employees/E0001'])]
    #[TestWith(['POST', '/employees/E0001/recommendations'])]
    #[TestWith(['POST', '/employees/E0001/complete'])]
    #[TestWith(['GET', '/hr'])]
    #[TestWith(['GET', '/admin/upload'])]
    #[TestWith(['POST', '/admin/upload'])]
    #[TestWith(['GET', '/hr/events'])]
    #[TestWith(['POST', '/hr/events'])]
    #[TestWith(['POST', '/session/role'])]
    public function test_guest_with_legacy_hr_session_receives_401(string $method, string $path): void
    {
        $this->withSession(['role' => 'hr', 'employee_id' => 'E0001']);

        $this->json($method, $path, ['role' => 'hr'])->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_employee_cannot_assign_hr_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['role' => 'employee']);

        $this->postJson('/session/role', ['role' => 'hr'])->assertForbidden()->assertSessionHas('role', 'employee');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'employee']);
    }

    #[TestWith(['GET', '/hr'])]
    #[TestWith(['GET', '/admin/upload'])]
    #[TestWith(['POST', '/admin/upload'])]
    #[TestWith(['GET', '/hr/events'])]
    #[TestWith(['GET', '/hr/events/create'])]
    #[TestWith(['GET', '/hr/events/EV_006/edit'])]
    #[TestWith(['POST', '/hr/events'])]
    #[TestWith(['PUT', '/hr/events/EV_006'])]
    #[TestWith(['DELETE', '/hr/events/EV_006'])]
    public function test_employee_with_legacy_hr_session_receives_403_on_privileged_routes(string $method, string $path): void
    {
        $this->import();
        $this->actingAs(User::factory()->create(['employee_id' => 'E0001']))->withSession(['role' => 'hr']);

        $this->json($method, $path)->assertForbidden();

        $this->assertDatabaseCount('events', 40);
        $this->assertDatabaseCount('employees', 200);
        $this->assertDatabaseCount('activity_records', 2743);
    }

    public function test_employee_identity_comes_from_account_even_with_forged_session_and_request_fields(): void
    {
        $this->import();
        $this->actingAs(User::factory()->create(['employee_id' => 'E0002']))
            ->withSession(['role' => 'hr', 'employee_id' => 'E0001']);

        $this->getJson('/employees?employee_id=E0001')->assertJsonPath('employees.total', 1)
            ->assertJsonPath('employees.data.0.employee_id', 'E0002');
        $this->get('/employees')->assertDontSee('id="session-role"', false)
            ->assertDontSee(route('hr.index'))->assertDontSee(route('admin.upload'));
        $this->getJson('/employees/E0001')->assertNotFound();
        $this->postJson('/employees/E0001/recommendations', ['role' => 'hr'])->assertNotFound();
        $this->postJson('/employees/E0001/complete', ['event_id' => 'EV_036'])->assertNotFound();

        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseCount('activity_records', 2743);
    }

    public function test_account_without_employee_link_has_no_default_profile(): void
    {
        $this->import();
        $this->actingAs(User::factory()->create())->withSession(['employee_id' => 'E0001']);

        $this->getJson('/employees')->assertJsonPath('employees.total', 0);
        $this->getJson('/employees/E0001')->assertNotFound();
    }

    public function test_login_uses_assigned_role_ignores_privilege_fields_and_rotates_session(): void
    {
        $user = User::factory()->create(['email' => 'employee@example.com']);
        $this->withSession(['role' => 'hr', 'employee_id' => 'E0001']);
        $sessionId = session()->getId();

        $this->postJson('/login', [
            'email' => 'EMPLOYEE@example.com', 'password' => 'password',
            'role' => 'hr', 'employee_id' => 'E0001',
        ])->assertExactJson(['role' => 'employee'])->assertSessionHas('role', 'employee')
            ->assertSessionMissing('employee_id');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'employee', 'employee_id' => null]);
    }

    public function test_invalid_password_does_not_authenticate_or_flash_password(): void
    {
        User::factory()->create(['email' => 'employee@example.com']);

        $this->from('/login')->post('/login', ['email' => 'employee@example.com', 'password' => 'incorrect'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Неверный email или пароль.'])
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/login')->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_returns_422_for_array_email_before_authentication(): void
    {
        $this->postJson('/login', ['email' => ['employee@example.com'], 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_form_renders_after_invalid_array_email_submission(): void
    {
        $this->from('/login')->post('/login', ['email' => ['employee@example.com'], 'password' => 'password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->get('/login')->assertOk()->assertSeeText('Войти');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'missing@example.com', 'password' => 'incorrect'])->assertUnprocessable();
        }

        $this->postJson('/login', ['email' => 'missing@example.com', 'password' => 'incorrect'])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_hr_login_and_logout_enforce_access_on_subsequent_requests(): void
    {
        $this->import();
        $user = User::factory()->create(['role' => 'hr']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirectToRoute('employees.index')->assertSessionHas('role', 'hr');
        $this->getJson('/employees')->assertJsonPath('employees.total', 200);
        $this->get('/admin/upload')->assertOk();
        $this->post('/logout')->assertRedirectToRoute('login')->assertSessionMissing('role');

        $this->assertGuest();
        $this->getJson('/employees')->assertUnauthorized();
        $this->postJson('/admin/upload')->assertUnauthorized();
    }

    public function test_revoked_hr_role_is_effective_with_existing_authenticated_session(): void
    {
        $this->import();
        $user = User::factory()->create(['role' => 'hr', 'employee_id' => 'E0001']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHas('role', 'hr');
        User::whereKey($user->id)->update(['role' => 'employee']);
        Auth::forgetGuards();

        $this->getJson('/employees')->assertJsonPath('employees.total', 1);
        $this->getJson('/hr')->assertForbidden();
        $this->postJson('/session/role', ['role' => 'hr'])->assertForbidden();
    }

    private function import(): void
    {
        app(DatasetImporter::class)->import(base_path('docs/case_1/career_quest_dataset'));
    }
}
