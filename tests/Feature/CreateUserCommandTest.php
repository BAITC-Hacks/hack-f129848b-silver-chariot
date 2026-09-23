<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RoleProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_employee_account_with_profile_name_and_hashed_password(): void
    {
        $employee = $this->employee();

        $this->artisan('users:create', ['email' => 'Employee@example.com', '--employee' => $employee->employee_id])
            ->expectsQuestion('Password', 'unique-test-password')
            ->expectsOutput('Created employee@example.com with employee access.')
            ->assertSuccessful();

        $user = User::where('email', 'employee@example.com')->firstOrFail();
        $this->assertSame('Private Test Name', $user->name);
        $this->assertSame('employee', $user->role);
        $this->assertSame('E_TEST', $user->employee_id);
        $this->assertTrue(Hash::check('unique-test-password', $user->password));
    }

    public function test_creates_hr_account_without_employee_profile(): void
    {
        $this->artisan('users:create', ['email' => 'hr@example.com', '--name' => 'HR Operator', '--hr' => true])
            ->expectsQuestion('Password', 'another-test-password')
            ->expectsOutput('Created hr@example.com with hr access.')
            ->assertSuccessful();

        $user = User::where('email', 'hr@example.com')->firstOrFail();
        $this->assertSame('HR Operator', $user->name);
        $this->assertSame('hr', $user->role);
        $this->assertNull($user->employee_id);
        $this->assertTrue(Hash::check('another-test-password', $user->password));
    }

    #[DataProvider('invalidAccountOptions')]
    public function test_rejects_invalid_account_options_before_password_prompt(array $options, string $message): void
    {
        $this->artisan('users:create', ['email' => 'new@example.com', '--name' => 'New User', ...$options])
            ->expectsOutput($message)
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }

    /**
     * @return array<string, array{array<string, string|bool>, string}>
     */
    public static function invalidAccountOptions(): array
    {
        return [
            'unknown employee' => [['--employee' => 'MISSING'], 'The selected employee id is invalid.'],
            'employee required' => [[], 'The employee id field is required unless role is in hr.'],
            'hr name required' => [['--hr' => true, '--name' => ' '], 'The name field is required.'],
            'invalid email' => [['email' => 'not-an-email', '--hr' => true], 'The email field must be a valid email address.'],
        ];
    }

    public function test_rejects_duplicate_email_without_changing_existing_access(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('users:create', ['email' => 'EXISTING@example.com', '--name' => 'HR Operator', '--hr' => true])
            ->expectsOutput('The email has already been taken.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('employee', $user->fresh()->role);
    }

    public function test_rejects_employee_profile_already_linked_to_an_account(): void
    {
        $employee = $this->employee();
        User::factory()->create(['employee_id' => $employee->employee_id]);

        $this->artisan('users:create', ['email' => 'new@example.com', '--employee' => $employee->employee_id])
            ->expectsOutput('The employee id has already been taken.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    #[DataProvider('invalidPasswords')]
    public function test_rejects_invalid_password_without_creating_account(string $password, string $message): void
    {
        $this->artisan('users:create', ['email' => 'hr@example.com', '--name' => 'HR Operator', '--hr' => true])
            ->expectsQuestion('Password', $password)
            ->expectsOutput($message)
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'empty' => ['', 'The password field is required.'],
            'too short' => ['short', 'The password field must be at least 12 characters.'],
            'too long' => [str_repeat('a', 73), 'The password must not exceed 72 bytes.'],
            'too many bytes' => [str_repeat('я', 37), 'The password must not exceed 72 bytes.'],
        ];
    }

    public function test_rejects_noninteractive_account_creation(): void
    {
        $this->artisan('users:create', ['email' => 'hr@example.com', '--name' => 'HR Operator', '--hr' => true, '--no-interaction' => true])
            ->expectsOutput('Run users:create interactively to enter a hidden password.')
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }

    public function test_database_seeder_does_not_create_default_credentials(): void
    {
        $this->seed();

        $this->assertDatabaseEmpty('users');
    }

    private function employee(): Employee
    {
        RoleProfile::create(['role' => 'Backend Engineer', 'grade' => 'Middle', 'required_skills' => [], 'critical_skills' => []]);

        return Employee::create([
            'employee_id' => 'E_TEST', 'full_name' => 'Private Test Name', 'department' => 'Engineering',
            'role' => 'Backend Engineer', 'grade' => 'Middle', 'hire_date' => '2024-01-01', 'tenure_months' => 32,
            'work_format' => 'hybrid', 'preferred_language' => 'ru', 'skills' => [], 'last_review_date' => '2026-09-01',
        ]);
    }
}
