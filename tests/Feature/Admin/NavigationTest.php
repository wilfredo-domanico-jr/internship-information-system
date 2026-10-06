<?php

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

it('lists the admin sections once their routes exist', function () {
    foreach (['interns', 'advisers', 'companies', 'partners', 'classes', 'departments', 'imports', 'archive'] as $r) {
        Route::get("/_t/{$r}", fn () => '')->name("admin.{$r}.index");
    }
    Route::getRoutes()->refreshNameLookups();
    $admin = User::factory()->admin()->create();

    $labels = collect(Navigation::for($admin))->pluck('label')->all();

    expect($labels)->toBe(['Dashboard', 'Interns', 'Advisers', 'Companies', 'Partner companies', 'Classes', 'Departments', 'Imports', 'Archive', 'Notifications']);
});

it('lists the adviser classroom section once its route exists', function () {
    Route::get('/_t/adviser-classes', fn () => '')->name('adviser.classes.index');
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->adviser()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My classes', 'Notifications']);
});

it('lists the intern classroom sections once their routes exist', function () {
    Route::get('/_t/intern-class', fn () => '')->name('intern.class.show');
    Route::get('/_t/intern-submissions', fn () => '')->name('intern.submissions.index');
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->intern()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My class', 'My submissions', 'Notifications']);
});

it('does not show portal sections to roles that have none', function () {
    $company = User::factory()->company()->create();

    expect(collect(Navigation::for($company))->pluck('label')->all())->toBe(['Dashboard', 'Notifications']);
});
