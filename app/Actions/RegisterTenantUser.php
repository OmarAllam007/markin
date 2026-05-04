<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterTenantUser
{
    /**
     * @param  array{company_name: string, number_of_employees: int, name: string, country_code: string, mobile: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $name = trim($data['company_name']);
            if ($name === '') {
                throw new InvalidArgumentException('Company name is required.');
            }

            $tenant = Tenant::query()
                ->whereNull('parent_id')
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($tenant === null) {
                $tenant = Tenant::query()->create([
                    'name' => $name,
                    'number_of_employees' => (int) $data['number_of_employees'],
                ]);
            }

            $user = User::query()->create([
                'name' => $data['name'],
                'country_code' => $data['country_code'],
                'mobile' => $data['mobile'],
                'email' => $data['email'],
                'password' => $data['password'],
                'current_tenant_id' => $tenant->id,
            ]);

            $tenant->users()->attach($user->id, [
                'is_admin' => true,
                'is_supervisor' => false,
                'status' => UserStatus::Active->value,
            ]);

            return $user;
        });
    }
}
