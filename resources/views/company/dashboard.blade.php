<x-layouts.app title="Dashboard">
    <x-page-header :title="$company->name" :subtitle="'Hello, '.auth()->user()->first_name.'. Here is your internship program at a glance.'">
        <x-slot:actions><x-badge :status="$company->approval_status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active interns" :value="$stats['active_interns']" icon="heroicon-o-users" />
        <x-stat-card label="Open postings" :value="$stats['open_postings']" icon="heroicon-o-megaphone" color="sky" />
        <x-stat-card label="DTRs to review" :value="$stats['pending_dtrs']" icon="heroicon-o-clipboard-document-check" color="amber" />
        <x-stat-card label="Certificates issued" :value="$stats['certificates']" icon="heroicon-o-trophy" color="green" />
    </div>

    <x-card title="Company code" subtitle="Accepted interns enter this code to join your company.">
        <div x-data="{ copied: false, code: @js($company->company_code) }" class="flex flex-wrap items-center gap-3">
            <code class="rounded-xl bg-stone-100 px-4 py-2 font-mono text-lg font-semibold tracking-widest dark:bg-stone-800">{{ $company->company_code }}</code>
            <x-button type="button" variant="secondary" icon="heroicon-o-clipboard"
                      @click="navigator.clipboard.writeText(code).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
            </x-button>
        </div>
    </x-card>
</x-layouts.app>
