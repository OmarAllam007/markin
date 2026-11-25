<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Services\TicketService;
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
            ->with([
                'subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                'subcategories.items' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (TicketCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'name_translations' => $category->name_translations,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'ticket_type' => $category->ticket_type?->value,
                'ticket_type_label' => $category->ticket_type?->label(),
                'has_subcategories' => $category->subcategories->isNotEmpty(),
                'subcategories' => $category->subcategories->map(fn ($sub) => [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'name_translations' => $sub->name_translations,
                    'items' => $sub->items->map->only(['id', 'name', 'name_translations'])->values(),
                ])->values(),
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
            ->get(['id', 'name', 'name_translations', 'description']);

        if ($subcategories->isNotEmpty()) {
            return ApiResponse::success('Subcategories retrieved.', [
                'type' => 'subcategories',
                'subcategories' => $subcategories->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'name_translations' => $s->name_translations,
                    'description' => $s->description,
                ]),
            ]);
        }

        return ApiResponse::success('Form fields retrieved.', array_merge(
            ['type' => 'form'],
            $this->buildFormPayload($category, $employee->tenant_id),
        ));
    }

    /** Same resolved, ordered field list the web form and the designer preview use. */
    public static function buildFormPayload(TicketCategory $category, int $tenantId, ?int $subcategoryId = null, ?int $itemId = null): array
    {
        $subcategory = $subcategoryId ? $category->subcategories()->find($subcategoryId) : null;
        $item = $subcategory && $itemId ? $subcategory->items()->find($itemId) : null;

        return [
            'ticket_type' => $category->ticket_type?->value,
            'ticket_type_label' => $category->ticket_type?->label(),
            'fields' => app(TicketService::class)->formFields($category, $subcategory, $item),
            'priorities' => TicketPriority::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color', 'is_default']),
        ];
    }
}
