<?php

namespace App\Console\Commands;

use App\Actions\RegisterTenantUser;
use Illuminate\Console\Command;

// ponytail: replaces the public /register page — onboarding happens by hand after a demo call.
class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {--company= : Company name}
        {--employees=10 : Headcount}
        {--name= : Admin full name}
        {--country-code= : Admin mobile country code, e.g. +966}
        {--mobile= : Admin mobile number}
        {--email= : Admin email}
        {--password= : Admin password}';

    protected $description = 'Create a company and its first admin user (already verified)';

    public function handle(RegisterTenantUser $registerTenantUser): int
    {
        $data = [
            'company_name' => $this->option('company') ?: $this->ask('Company name'),
            'number_of_employees' => (int) ($this->option('employees') ?: 10),
            'name' => $this->option('name') ?: $this->ask('Admin full name'),
            'country_code' => $this->option('country-code') ?: $this->ask('Mobile country code (e.g. +966)'),
            'mobile' => $this->option('mobile') ?: $this->ask('Mobile number'),
            'email' => $this->option('email') ?: $this->ask('Admin email'),
            'password' => $this->option('password') ?: $this->secret('Admin password'),
        ];

        $user = $registerTenantUser->execute($data);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Created {$data['company_name']} with admin {$user->email}.");

        return self::SUCCESS;
    }
}
