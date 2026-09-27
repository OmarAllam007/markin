<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreSubcategoryRequest;
use App\Http\Requests\Ticketing\Admin\UpdateSubcategoryRequest;
use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubcategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $subcategories = TicketSubcategory::query()
            ->where('tenant_id', $tenantId)
            ->with('category:id,name')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ticketing/admin/subcategories/Index', [
            'subcategories' => $subcategories,
            'categories' => $categories,
            'filters' => ['search' => $request->input('search'), 'category_id' => $request->input('category_id')],
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ticketing/admin/subcategories/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreSubcategoryRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        TicketSubcategory::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.subcategories.index')
            ->with('success', 'Subcategory created successfully.');
    }

    public function edit(Request $request, TicketSubcategory $subcategory): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        abort_if($subcategory->tenant_id !== $tenantId, 403);

        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ticketing/admin/subcategories/Edit', [
            'subcategory' => $subcategory->only(['id', 'category_id', 'name', 'name_ar', 'description', 'is_active']),
            'categories' => $categories,
        ]);
    }

    public function update(UpdateSubcategoryRequest $request, TicketSubcategory $subcategory): RedirectResponse
    {
        abort_if($subcategory->tenant_id !== $request->user()->current_tenant_id, 403);

        $subcategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.subcategories.index')
            ->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(Request $request, TicketSubcategory $subcategory): RedirectResponse
    {
        abort_if($subcategory->tenant_id !== $request->user()->current_tenant_id, 403);

        $subcategory->delete();

        return redirect()->route('ticketing.admin.subcategories.index')
            ->with('success', 'Subcategory deleted successfully.');
    }
}
