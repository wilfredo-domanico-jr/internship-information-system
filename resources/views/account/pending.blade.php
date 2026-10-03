<x-layouts.auth title="Account verification">
    @php $rejected = $company->approval_status === \App\Enums\ApprovalStatus::Rejected; @endphp
    <div class="card p-6">
        <span class="grid size-12 place-items-center rounded-2xl {{ $rejected ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' }}">
            @if ($rejected)<x-heroicon-o-x-circle class="size-6" />@else<x-heroicon-o-clock class="size-6" />@endif
        </span>
        <h2 class="mt-4 font-display text-2xl font-semibold">{{ $rejected ? 'Registration not approved' : 'Verification pending' }}</h2>
        <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
            @if ($rejected)
                The {{ config('wiis.institution.office') }} could not verify <strong>{{ $company->name }}</strong>. Please contact {{ config('wiis.support.email') }} for details.
            @else
                Thanks for registering <strong>{{ $company->name }}</strong>. Your business permit and MOA are being reviewed by the {{ config('wiis.institution.office') }}. You will be able to post internships once your account is approved.
            @endif
        </p>
        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-stone-500">Status</dt><dd class="mt-1"><x-badge :status="$company->approval_status" /></dd></div>
            <div><dt class="text-stone-500">Submitted</dt><dd class="mt-1">{{ $company->created_at->format('M j, Y') }}</dd></div>
        </dl>
        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <x-button variant="secondary" class="w-full">Sign out</x-button>
            </form>
        @endif
    </div>
</x-layouts.auth>
