<?php

use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('shows the three import cards with template links', function () {
    $this->actingAs($this->admin)->get(route('admin.imports.index'))
        ->assertOk()->assertSee('Interns')->assertSee('Advisers')->assertSee('Classes')
        ->assertSee(route('admin.imports.template', 'interns'))
        ->assertSee(route('admin.imports.template', 'classes'));
});

it('downloads an xlsx template per type and 404s for unknown types', function (string $type) {
    $this->actingAs($this->admin)->get(route('admin.imports.template', $type))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload("wiis-{$type}-template.xlsx");
})->with(['interns', 'advisers', 'classes']);

it('rejects unknown template types', function () {
    $this->actingAs($this->admin)->get(route('admin.imports.template', 'payroll'))->assertNotFound();
});

it('forbids non-admins and redirects guests', function () {
    $intern = User::factory()->intern()->create();

    $this->actingAs($intern)->get(route('admin.imports.index'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.imports.template', 'interns'))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('admin.imports.index'))->assertRedirect(route('login'));
});
