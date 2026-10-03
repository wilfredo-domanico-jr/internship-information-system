<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Sidebar items for the user's portal. Later phases append to the per-role lists.
     *
     * @return array<int, array{label: string, route: string, icon: string, active: string, badge?: int|string|null}>
     */
    public static function for(User $user): array
    {
        $portal = $user->role->value;

        $items = [
            ['label' => 'Dashboard', 'route' => "{$portal}.dashboard", 'icon' => 'heroicon-o-home', 'active' => "{$portal}.dashboard"],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => 'notifications.*',
                'badge' => $user->unreadNotifications()->count() ?: null],
        ];

        return array_values(array_filter($items, fn (array $item) => Route::has($item['route'])));
    }
}
