@if (Route::has('admin.users.disable') && ! $user->isAdmin())
    <x-card title="Account status" class="lg:col-span-3 border-rose-200 dark:border-rose-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm">This account is <x-badge :status="$user->status" />.</p>
                <p class="mt-1 text-sm text-stone-500">Disabled accounts cannot sign in and are signed out immediately. Nothing is deleted; you can reactivate at any time.</p>
            </div>
            @if ($user->isDisabled())
                <x-confirm-form :action="route('admin.users.reactivate', $user)" :confirm="'Reactivate '.$user->name.'?'">
                    <x-button icon="heroicon-o-arrow-path">Reactivate account</x-button>
                </x-confirm-form>
            @else
                <x-confirm-form :action="route('admin.users.disable', $user)" :confirm="'Disable '.$user->name.'? They will be signed out immediately.'">
                    <x-button variant="danger" icon="heroicon-o-no-symbol">Disable account</x-button>
                </x-confirm-form>
            @endif
        </div>
    </x-card>
@endif
