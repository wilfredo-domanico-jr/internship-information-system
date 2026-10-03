<?php

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

it('lists the admin sections once their routes exist', function () {
    // Register throwaway routes so Route::has() passes without the later tasks.
    foreach (['interns', 'advisers', 'companies', 'partners', 'classes', 'departments', 'imports', 'archive'] as $r) {
        Route::get("/_t/{$r}", fn () => '')->name("admin.{$r}.index");
    }
    Route::getRoutes()->refreshNameLookups();
    $admin = User::factory()->admin()->create();

    $labels = collect(Navigation::for($admin))->pluck('label')->all();

    expect($labels)->toBe(['Dashboard', 'Interns', 'Advisers', 'Companies', 'Partner companies', 'Classes', 'Departments', 'Imports', 'Archive', 'Notifications']);
});

it('does not show admin sections to other roles', function () {
    $intern = User::factory()->intern()->create();

    expect(collect(Navigation::for($intern))->pluck('label')->all())->toBe(['Dashboard', 'Notifications']);
});
