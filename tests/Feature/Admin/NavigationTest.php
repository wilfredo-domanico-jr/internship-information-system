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

it('lists the intern sections once their routes exist', function () {
    foreach (['intern.class.show', 'intern.submissions.index', 'intern.postings.index', 'intern.applications.index', 'intern.internship.show', 'intern.dtrs.index', 'intern.requests.index', 'intern.certificates.index'] as $i => $name) {
        Route::get("/_t/i{$i}", fn () => '')->name($name);
    }
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->intern()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My class', 'My submissions', 'Internships', 'My applications', 'My internship', 'DTRs', 'Requests', 'Certificates', 'Notifications']);
});

it('lists the company sections once their routes exist', function () {
    foreach (['company.postings.index', 'company.interviews.index', 'company.interns.index', 'company.dtrs.index', 'company.requests.index', 'company.certificates.index', 'company.history.index'] as $i => $name) {
        Route::get("/_t/c{$i}", fn () => '')->name($name);
    }
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->company()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'Postings', 'Interviews', 'Interns', 'DTRs', 'Requests', 'Certificates', 'History', 'Notifications']);
});

it('does not show portal sections to roles that have none', function () {
    $company = User::factory()->company()->create();

    expect(collect(Navigation::for($company))->pluck('label')->all())->toBe(['Dashboard', 'Notifications']);
});
