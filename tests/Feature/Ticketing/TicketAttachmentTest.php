<?php

use App\Enums\AppModule;
use App\Models\Attachment;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function submitTicketWithAttachment(User $admin, Tenant $tenant): Attachment
{
    TenantModule::create(['tenant_id' => $tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);

    $category = TicketCategory::create(['tenant_id' => $tenant->id, 'name' => 'HR', 'color' => '#000000', 'is_active' => true]);

    test()->actingAs($admin)->post(route('ticketing.tickets.store'), [
        'subject' => 'Medical leave',
        'description' => 'Report attached',
        'category_id' => $category->id,
        'attachments' => [UploadedFile::fake()->create('medical-report.pdf', 100, 'application/pdf')],
    ])->assertRedirect();

    return Attachment::sole();
}

it('stores ticket attachments on the private disk, not the public one', function () {
    Storage::fake('local');
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $attachment = submitTicketWithAttachment(User::factory()->admin($tenant)->create(), $tenant);

    Storage::disk('local')->assertExists($attachment->file_path);
    Storage::disk('public')->assertMissing($attachment->file_path);
});

it('lets a user of the same tenant download the attachment', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();
    $attachment = submitTicketWithAttachment($admin, $tenant);

    $this->actingAs($admin)->get(route('ticketing.attachments.show', $attachment))
        ->assertOk()
        ->assertDownload('medical-report.pdf');
});

it('blocks guests and other tenants from downloading the attachment', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $attachment = submitTicketWithAttachment(User::factory()->admin($tenant)->create(), $tenant);

    auth()->logout();
    $this->get(route('ticketing.attachments.show', $attachment))->assertRedirect(route('login'));

    $otherTenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $otherTenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);

    $this->actingAs(User::factory()->admin($otherTenant)->create())
        ->get(route('ticketing.attachments.show', $attachment))
        ->assertForbidden();
});
