<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** @return array<int, LaravelRoute> */
function adminRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), 'admin.'))
        ->values()->all();
}

function adminRouteUrl(LaravelRoute $route, array $params): string
{
    return route($route->getName(), array_intersect_key($params, array_flip($route->parameterNames())));
}

beforeEach(function () {
    $this->params = [
        'user' => User::factory()->intern()->create()->id,
        'company' => Company::factory()->registered()->create()->id,
        'classSection' => ClassSection::factory()->create()->id,
        'department' => Department::factory()->create()->id,
        'type' => 'interns',
    ];
});

it('discovers the admin routes', function () {
    expect(count(adminRoutes()))->toBeGreaterThan(20);
});

it('forbids every admin route to non-admin roles', function () {
    foreach (['intern', 'adviser', 'company'] as $role) {
        $user = User::factory()->{$role}()->create();
        foreach (adminRoutes() as $route) {
            $unknown = array_diff($route->parameterNames(), array_keys($this->params));
            expect($unknown)->toBe([], "{$route->getName()} needs a fixture for ".implode(', ', $unknown));

            $this->actingAs($user)->call($route->methods()[0], adminRouteUrl($route, $this->params))
                ->assertForbidden("{$role} reached {$route->getName()}");
        }
    }
});

it('redirects guests from every admin route to the login page', function () {
    foreach (adminRoutes() as $route) {
        $this->call($route->methods()[0], adminRouteUrl($route, $this->params))
            ->assertRedirect(route('login'));
    }
});

it('returns 404 when an adviser page is opened with an intern id', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.advisers.show', $this->params['user']))
        ->assertNotFound();
});
