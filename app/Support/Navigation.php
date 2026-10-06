<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Sidebar items for the user's portal.
     *
     * @return array<int, array{label: string, route: string, icon: string, active: string|array<int, string>, badge?: int|string|null}>
     */
    public static function for(User $user): array
    {
        $portal = $user->role->value;

        $items = [
            ['label' => 'Dashboard', 'route' => "{$portal}.dashboard", 'icon' => 'heroicon-o-home', 'active' => "{$portal}.dashboard"],
            ...static::portalItems($user),
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => 'notifications.*',
                'badge' => $user->unreadNotifications()->count() ?: null],
        ];

        return array_values(array_filter($items, fn (array $item) => Route::has($item['route'])));
    }

    /** @return array<int, array{label: string, route: string, icon: string, active: string|array<int, string>, badge?: int|string|null}> */
    private static function portalItems(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                ['label' => 'Interns', 'route' => 'admin.interns.index', 'icon' => 'heroicon-o-academic-cap', 'active' => 'admin.interns.*'],
                ['label' => 'Advisers', 'route' => 'admin.advisers.index', 'icon' => 'heroicon-o-user-group', 'active' => 'admin.advisers.*'],
                ['label' => 'Companies', 'route' => 'admin.companies.index', 'icon' => 'heroicon-o-building-office-2', 'active' => 'admin.companies.*',
                    'badge' => Company::registered()->pending()->count() ?: null],
                ['label' => 'Partner companies', 'route' => 'admin.partners.index', 'icon' => 'heroicon-o-building-storefront', 'active' => 'admin.partners.*'],
                ['label' => 'Classes', 'route' => 'admin.classes.index', 'icon' => 'heroicon-o-rectangle-group', 'active' => 'admin.classes.*'],
                ['label' => 'Departments', 'route' => 'admin.departments.index', 'icon' => 'heroicon-o-squares-2x2', 'active' => 'admin.departments.*'],
                ['label' => 'Imports', 'route' => 'admin.imports.index', 'icon' => 'heroicon-o-arrow-up-tray', 'active' => 'admin.imports.*'],
                ['label' => 'Archive', 'route' => 'admin.archive.index', 'icon' => 'heroicon-o-archive-box', 'active' => 'admin.archive.*'],
            ];
        }

        if ($user->isAdviser()) {
            return [
                ['label' => 'My classes', 'route' => 'adviser.classes.index', 'icon' => 'heroicon-o-rectangle-group',
                    'active' => ['adviser.classes.*', 'adviser.announcements.*', 'adviser.comments.*', 'adviser.folders.*', 'adviser.submissions.*', 'adviser.resources.*']],
            ];
        }

        if ($user->isIntern()) {
            return [
                ['label' => 'My class', 'route' => 'intern.class.show', 'icon' => 'heroicon-o-rectangle-group',
                    'active' => ['intern.class.*', 'intern.comments.*', 'intern.folders.*']],
                ['label' => 'My submissions', 'route' => 'intern.submissions.index', 'icon' => 'heroicon-o-document-check', 'active' => 'intern.submissions.*'],
            ];
        }

        return [];
    }
}
