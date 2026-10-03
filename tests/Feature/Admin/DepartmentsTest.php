<?php

use App\Models\Department;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists departments with usage counts and manages them', function () {
    $it = Department::factory()->create(['name' => 'Information Technology']);
    Placement::factory()->count(2)->create(['department_id' => $it->id]);

    $this->actingAs($this->admin)->get(route('admin.departments.index'))->assertOk()->assertSee('Information Technology')->assertSee('2');

    $this->actingAs($this->admin)->post(route('admin.departments.store'), ['name' => 'Finance'])->assertRedirect()->assertSessionHas('success');
    expect(Department::where('name', 'Finance')->exists())->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.departments.update', $it), ['name' => 'IT & Systems'])->assertRedirect();
    expect($it->refresh()->name)->toBe('IT & Systems');

    $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $it))->assertRedirect()->assertSessionHas('success');
    expect(Department::find($it->id))->toBeNull()
        ->and(Placement::whereNull('department_id')->count())->toBe(2);
});

it('requires unique names, ignoring the record being edited', function () {
    $a = Department::factory()->create(['name' => 'Finance']);
    Department::factory()->create(['name' => 'Marketing']);

    $this->actingAs($this->admin)->post(route('admin.departments.store'), ['name' => 'finance'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->put(route('admin.departments.update', $a), ['name' => 'Marketing'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->put(route('admin.departments.update', $a), ['name' => 'Finance'])->assertSessionHasNoErrors();
});

it('is admin-only', function () {
    $this->actingAs(User::factory()->adviser()->create())->get(route('admin.departments.index'))->assertForbidden();
});
