<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('users:create {email : Login email} {--name= : Account name; defaults to the employee name} {--employee= : Employee ID} {--hr : Grant HR access}')]
#[Description('Create an account with administrator-assigned access and a hidden password prompt')]
class CreateUser extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run users:create interactively to enter a hidden password.');

            return self::FAILURE;
        }

        $employeeId = trim((string) $this->option('employee')) ?: null;
        $employee = $employeeId === null ? null : Employee::find($employeeId);
        $data = [
            'email' => Str::lower(trim((string) $this->argument('email'))),
            'name' => trim((string) ($this->option('name') ?? $employee?->full_name)),
            'role' => $this->option('hr') ? 'hr' : 'employee',
            'employee_id' => $employeeId,
        ];
        $validator = Validator::make($data, [
            'email' => 'required|email|max:255|unique:users,email',
            'employee_id' => 'required_unless:role,hr|nullable|exists:employees,employee_id|unique:users,employee_id',
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Password', false);
        $validator = Validator::make(['password' => $password], ['password' => 'required|string|min:12']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if (strlen($password) > 72) {
            $this->error('The password must not exceed 72 bytes.');

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $password;
        $user->role = $data['role'];
        $user->employee_id = $employeeId;
        $user->save();

        $this->info("Created {$user->email} with {$user->role} access.");

        return self::SUCCESS;
    }
}
