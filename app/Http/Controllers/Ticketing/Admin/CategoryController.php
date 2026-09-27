<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreCategoryRequest;
use App\Http\Requests\Ticketing\Admin\UpdateCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->withCount('subcategories')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ticketing/admin/categories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ticketing/admin/categories/Create', [
            'types' => collect(TicketType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        TicketCategory::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Request $request, TicketCategory $category): Response
    {
        abort_if($category->tenant_id !== $request->user()->current_tenant_id, 403);

        return Inertia::render('ticketing/admin/categories/Edit', [
            'category' => $category->only(['id', 'name', 'name_ar', 'description', 'icon', 'color', 'is_active', 'ticket_type']),
            'types' => collect(TicketType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function update(UpdateCategoryRequest $request, TicketCategory $category): RedirectResponse
    {
        abort_if($category->tenant_id !== $request->user()->current_tenant_id, 403);

        $category->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, TicketCategory $category): RedirectResponse
    {
        abort_if($category->tenant_id !== $request->user()->current_tenant_id, 403);

        $category->delete();

        return redirect()->route('ticketing.admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
