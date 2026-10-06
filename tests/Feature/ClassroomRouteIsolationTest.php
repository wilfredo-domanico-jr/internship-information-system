<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** @return array<int, LaravelRoute> */
function portalRoutes(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), $prefix))
        ->values()->all();
}

function portalRouteUrl(LaravelRoute $route, array $params): string
{
    return route($route->getName(), array_intersect_key($params, array_flip($route->parameterNames())));
}

beforeEach(function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $section->id]);
    $announcement = Announcement::factory()->for($section)->for($adviser, 'author')->create();
    $folder = ClassFolder::factory()->for($section)->create();

    $this->params = [
        'classSection' => $section->id,
        'announcement' => $announcement->id,
        'comment' => AnnouncementComment::factory()->for($announcement)->for($intern, 'author')->create()->id,
        'folder' => $folder->id,
        'submission' => ClassSubmission::factory()->for($folder, 'folder')->for($intern, 'intern')->create()->id,
        'resource' => ClassResource::factory()->for($section)->for($adviser, 'uploader')->create()->id,
    ];
});

it('discovers the classroom routes', function () {
    expect(count(portalRoutes('adviser.')))->toBeGreaterThan(20)
        ->and(count(portalRoutes('intern.')))->toBeGreaterThan(8);
});

it('forbids every adviser route to other roles and every intern route to other roles', function (string $prefix, array $others) {
    foreach ($others as $role) {
        $user = User::factory()->{$role}()->create();
        foreach (portalRoutes($prefix) as $route) {
            $unknown = array_diff($route->parameterNames(), array_keys($this->params));
            expect($unknown)->toBe([], "{$route->getName()} needs a fixture for ".implode(', ', $unknown));

            $this->actingAs($user)->call($route->methods()[0], portalRouteUrl($route, $this->params))
                ->assertForbidden("{$role} reached {$route->getName()}");
        }
    }
})->with([
    'adviser portal' => ['adviser.', ['admin', 'company', 'intern']],
    'intern portal' => ['intern.', ['admin', 'company', 'adviser']],
]);

it('redirects guests from every classroom route to the login page', function () {
    foreach ([...portalRoutes('adviser.'), ...portalRoutes('intern.')] as $route) {
        $this->call($route->methods()[0], portalRouteUrl($route, $this->params))->assertRedirect(route('login'));
    }
});
