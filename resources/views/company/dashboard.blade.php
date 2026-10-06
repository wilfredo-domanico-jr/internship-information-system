<x-layouts.app title="Dashboard">
    <x-page-header :title="$company->name" :subtitle="'Hello, '.auth()->user()->first_name.'. Here is your internship program at a glance.'">
        <x-slot:actions><x-badge :status="$company->approval_status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('company.interns.index') }}"><x-stat-card label="Active interns" :value="$stats['active_interns']" icon="heroicon-o-users" /></a>
        <a href="{{ route('company.postings.index') }}"><x-stat-card label="Open postings" :value="$stats['open_postings']" icon="heroicon-o-megaphone" color="sky" :hint="$stats['pending_applicants'].' pending applicants'" /></a>
        <a href="{{ route('company.dtrs.index') }}"><x-stat-card label="DTRs to review" :value="$stats['pending_dtrs']" icon="heroicon-o-clipboard-document-check" color="amber" /></a>
        <a href="{{ route('company.certificates.index') }}"><x-stat-card label="Certificates issued" :value="$stats['certificates']" icon="heroicon-o-trophy" color="green" /></a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Interns by hours rendered" subtitle="Active interns, grouped by the hours they have rendered with you." class="lg:col-span-2">
            <x-chart type="bar" :labels="$buckets['labels']" :datasets="[['label' => 'Interns', 'data' => $buckets['data']]]" :height="240" />
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                @foreach ($buckets['labels'] as $i => $label)
                    <div class="rounded-xl bg-stone-50 p-3 text-center dark:bg-stone-900"><dt class="text-xs text-stone-500">{{ $label }} h</dt><dd class="font-semibold tabular-nums">{{ $buckets['data'][$i] }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        <div class="space-y-4">
            <x-card title="Needs attention">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.postings.index') }}" class="hover:underline">Pending applicants</a><span class="font-semibold tabular-nums">{{ $stats['pending_applicants'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.interviews.index') }}" class="hover:underline">Upcoming interviews</a><span class="font-semibold tabular-nums">{{ $stats['upcoming_interviews'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.dtrs.index') }}" class="hover:underline">DTRs to review</a><span class="font-semibold tabular-nums">{{ $stats['pending_dtrs'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.requests.index') }}" class="hover:underline">Document requests</a><span class="font-semibold tabular-nums">{{ $stats['pending_requests'] }}</span></li>
                </ul>
            </x-card>

            <x-card title="Company code" subtitle="Accepted interns enter this code to join your company.">
                <div x-data="{ copied: false, code: @js($company->company_code) }" class="flex flex-wrap items-center gap-3">
                    <code class="rounded-xl bg-stone-100 px-4 py-2 font-mono text-lg font-semibold tracking-widest dark:bg-stone-800">{{ $company->company_code }}</code>
                    <x-button type="button" variant="secondary" icon="heroicon-o-clipboard"
                              @click="navigator.clipboard.writeText(code).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
