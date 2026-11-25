<?php

use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketSubcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Category → subcategory with a technician group (one member) configured on
 * the category, so tickets can be submitted against it.
 *
 * @return array{category: TicketCategory, subcategory: TicketSubcategory, group: TicketGroup, technician: User}
 */
function configuredTicketService(Tenant $tenant, array $categoryAttributes = []): array
{
    $technician = User::factory()->withTenant($tenant)->create();
    $group = TicketGroup::create(['tenant_id' => $tenant->id, 'name' => 'IT Desk', 'type' => 'technician', 'is_active' => true]);
    $group->members()->attach($technician);

    $category = TicketCategory::create([
        'tenant_id' => $tenant->id,
        'name' => 'IT Support',
        'color' => '#000000',
        'is_active' => true,
        'group_id' => $group->id,
        ...$categoryAttributes,
    ]);
    $subcategory = $category->subcategories()->create(['tenant_id' => $tenant->id, 'name' => 'Hardware', 'is_active' => true]);

    return compact('category', 'subcategory', 'group', 'technician');
}
