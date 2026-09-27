<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TicketCategory;
use App\Models\TicketTypeCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $categories = TicketCategory::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('is_active', true)
            ->withCount(['subcategories' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn (TicketCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'name_ar' => $category->name_ar,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'ticket_type' => $category->ticket_type?->value,
                'ticket_type_label' => $category->ticket_type?->label(),
                'has_subcategories' => $category->subcategories_count > 0,
            ]);

        return ApiResponse::success('Categories retrieved.', ['categories' => $categories]);
    }

    public function show(Request $request, TicketCategory $category): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ($category->tenant_id !== $employee->tenant_id) {
            return ApiResponse::error('Not found.', null, 404);
        }

        $subcategories = $category->subcategories()
            ->where('is_active', true)
            ->get(['id', 'name', 'name_ar', 'description']);

        if ($subcategories->isNotEmpty()) {
            return ApiResponse::success('Subcategories retrieved.', [
                'type' => 'subcategories',
                'subcategories' => $subcategories->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'name_ar' => $s->name_ar,
                    'description' => $s->description,
                ]),
            ]);
        }

        return ApiResponse::success('Form fields retrieved.', array_merge(
            ['type' => 'form'],
            $this->buildFormPayload($category, $employee->tenant_id),
        ));
    }

    public static function buildFormPayload(TicketCategory $category, int $tenantId): array
    {
        $baseFields = $category->ticket_type
            ? collect($category->ticket_type->baseFields())->map(fn ($f) => array_merge($f, ['is_base' => true]))
            : collect();

        $customFields = TicketTypeCustomField::query()
            ->where('tenant_id', $tenantId)
            ->when($category->ticket_type, fn ($q) => $q->where('ticket_type', $category->ticket_type->value))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TicketTypeCustomField $f) => [
                'key' => $f->field_key,
                'label' => $f->label,
                'type' => $f->type,
                'is_required' => $f->is_required,
                'is_base' => false,
                'options' => $f->options,
                'sort_order' => $f->sort_order,
            ]);

        $standardFields = collect([
            ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'is_required' => true, 'is_base' => false, 'options' => null, 'sort_order' => 0],
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'is_required' => false, 'is_base' => false, 'options' => null, 'sort_order' => 1],
        ]);

        return [
            'ticket_type' => $category->ticket_type?->value,
            'ticket_type_label' => $category->ticket_type?->label(),
            'fields' => $standardFields->concat($baseFields)->concat($customFields)->values(),
        ];
    }
}
