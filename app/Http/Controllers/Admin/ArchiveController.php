<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $role = Role::tryFrom((string) $request->query('role'));
        $q = trim((string) $request->query('q'));

        $users = User::disabled()
            ->with('company')
            ->when($role, fn (Builder $query) => $query->where('role', $role))
            ->when($q !== '', fn (Builder $query) => Search::any($query, ['first_name', 'last_name', 'email', 'member_no'], $q))
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.archive.index', ['users' => $users]);
    }
}
