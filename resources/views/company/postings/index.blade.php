<x-layouts.app title="Postings">
    <x-page-header title="Internship postings" subtitle="Open postings are visible to every intern.">
        <x-slot:actions><x-button :href="route('company.postings.create')" icon="heroicon-o-plus">New posting</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Position</th><th>City</th><th>Closes</th><th>Applicants</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($postings as $posting)
                <tr>
                    <td>
                        <p class="font-medium">{{ $posting->title }}</p>
                        <p class="text-xs text-stone-500">{{ $posting->vacancies }} {{ Str::plural('vacancy', $posting->vacancies) }} · posted {{ $posting->created_at->format('M j, Y') }}</p>
                    </td>
                    <td>{{ $posting->city }}</td>
                    <td class="text-stone-500">{{ $posting->closing_date?->format('M j, Y') ?? 'No closing date' }}</td>
                    <td>
                        @if (Route::has('company.postings.applicants'))
                            <a href="{{ route('company.postings.applicants', $posting) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $posting->applications_count }} applicants</a>
                        @else
                            <span class="font-medium">{{ $posting->applications_count }} applicants</span>
                        @endif
                        <p class="text-xs text-stone-500">{{ $posting->pending_applications_count }} pending</p>
                    </td>
                    <td>
                        <x-badge :status="$posting->status" />
                        @if ($posting->status === \App\Enums\PostingStatus::Open && ! $posting->isAcceptingApplications())<p class="mt-1 text-xs text-amber-600">Closing date passed</p>@endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            <x-button variant="ghost" :href="route('company.postings.edit', $posting)" icon="heroicon-o-pencil-square">Edit</x-button>
                            <form method="POST" action="{{ route('company.postings.toggle', $posting) }}">
                                @csrf
                                <x-button variant="ghost" :icon="$posting->status === \App\Enums\PostingStatus::Open ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open'">{{ $posting->status === \App\Enums\PostingStatus::Open ? 'Close' : 'Reopen' }}</x-button>
                            </form>
                            @if ($posting->applications_count === 0)
                                <x-confirm-form :action="route('company.postings.destroy', $posting)" method="DELETE" :confirm="'Delete “'.$posting->title.'”?'">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Delete</x-button>
                                </x-confirm-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No postings yet" description="Publish a posting so interns can apply." icon="heroicon-o-megaphone"><x-slot:action><x-button :href="route('company.postings.create')" icon="heroicon-o-plus">New posting</x-button></x-slot:action></x-empty-state></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
