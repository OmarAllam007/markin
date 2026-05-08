<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'created_by',
    'arabic_name',
    'english_name',
    'mobile_country_code',
    'mobile_number',
    'email',
    'nationality',
    'marital_status',
    'birth_date',
    'gender',
    'religion',
    'job_title_ar',
    'job_title_en',
    'employee_number',
    'social_security_number',
    'id_number',
    'working_start_date',
    'contract_end_date',
    'contract_type',
    'status',
    'department_id',
    'location_id',
    'check_biometrics',
    'send_reminders',
    'allow_remote_checkin',
    'allow_any_location_checkin',
    'work_shift_id',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
        'check_biometrics' => false,
        'send_reminders' => false,
        'allow_remote_checkin' => false,
        'allow_any_location_checkin' => false,
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'contract_type' => ContractType::class,
            'status' => EmployeeStatus::class,
            'birth_date' => 'date',
            'working_start_date' => 'date',
            'contract_end_date' => 'date',
            'check_biometrics' => 'boolean',
            'send_reminders' => 'boolean',
            'allow_remote_checkin' => 'boolean',
            'allow_any_location_checkin' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }
}
