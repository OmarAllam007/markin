<?php

namespace App\Models;

use App\Enums\RequestStatusType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'name_translations', 'type', 'color', 'sort_order', 'is_default'])]
class RequestStatus extends Model
{
    /** [name, type, color, is_default] created for every company. */
    public const DEFAULTS = [
        ['Open', 'open', '#3e97ff', true],
        ['In progress', 'open', '#7239ea', false],
        ['Waiting for approval', 'awaiting_approval', '#b07d00', false],
        ['On hold', 'on_hold', '#f6c000', false],
        ['Waiting for customer', 'on_hold', '#ff8f00', false],
        ['Resolved', 'resolved', '#17a673', false],
        ['Closed', 'closed', '#5e6278', false],
        ['Cancelled', 'cancelled', '#d9534f', false],
    ];

    protected function casts(): array
    {
        return [
            'type' => RequestStatusType::class,
            'name_translations' => 'array',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** The company's statuses in order, creating the defaults the first time. */
    public static function forTenant(int $tenantId): Collection
    {
        $statuses = static::where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('id')->get();

        if ($statuses->isEmpty()) {
            foreach (self::DEFAULTS as $i => [$name, $type, $color, $default]) {
                static::create(['tenant_id' => $tenantId, 'name' => $name, 'type' => $type, 'color' => $color, 'sort_order' => $i, 'is_default' => $default]);
            }

            return static::forTenant($tenantId);
        }

        return $statuses;
    }

    public static function awaitingApprovalFor(int $tenantId): ?self
    {
        return static::forTenant($tenantId)->firstWhere('type', RequestStatusType::AwaitingApproval);
    }

    public static function initialFor(int $tenantId): ?self
    {
        $statuses = static::forTenant($tenantId);

        return $statuses->firstWhere('is_default', true) ?? $statuses->firstWhere('type', RequestStatusType::Open);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
