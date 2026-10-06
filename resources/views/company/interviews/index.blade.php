<x-layouts.app title="Interviews">
    <x-page-header title="Interviews" subtitle="Applicants you have invited. Accept or decline after the interview." />

    <nav class="flex flex-wrap gap-2" aria-label="Interview groups">
        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
            <a href="{{ route('company.interviews.index', ['when' => $key]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $when === $key,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $when !== $key])>
                {{ $label }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>When</th><th>Applicant</th><th>Position</th><th>Venue</th><th class="text-right">Decision</th></x-slot:head>
            @forelse ($interviews as $interview)
                @php $application = $interview->application; @endphp
                <tr>
                    <td>
                        <p class="font-medium">{{ $interview->scheduled_on->format('D, M j') }}</p>
                        <p class="text-xs text-stone-500">{{ \Illuminate\Support\Carbon::parse($interview->starts_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($interview->ends_at)->format('g:i A') }}</p>
                    </td>
                    <td>
                        <a href="{{ route('company.applications.show', $application) }}" class="font-medium hover:underline">{{ $application->intern->name }}</a>
                        <p class="font-mono text-xs text-stone-500">{{ $application->intern->internProfile?->student_number }}</p>
                    </td>
                    <td>{{ $application->posting->title }}</td>
                    <td>
                        {{ $interview->venue }}
                        @if ($interview->link)<a href="{{ $interview->link }}" target="_blank" rel="noopener" class="ml-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Open link</a>@endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1" x-data="{ name: 'decline-{{ $application->id }}' }">
                            <x-confirm-form :action="route('company.applications.accept', $application)" :confirm="'Accept '.$application->intern->name.'?'">
                                <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Accept</x-button>
                            </x-confirm-form>
                            <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', name)">Decline</x-button>
                        </div>
                        <x-modal :name="'decline-'.$application->id" title="Decline applicant">
                            <form method="POST" action="{{ route('company.applications.decline', $application) }}" class="space-y-4" x-data="{ name: 'decline-{{ $application->id }}' }"
                                  x-init="@if ($errors->has('reason') && (string) old('application_id') === (string) $application->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <p class="text-sm text-stone-600 dark:text-stone-300">The reason is sent to {{ $application->intern->name }}.</p>
                                <div>
                                    <label for="reason-{{ $application->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                    <textarea id="reason-{{ $application->id }}" name="reason" rows="3" required maxlength="500" class="input">{{ (string) old('application_id') === (string) $application->id ? old('reason') : '' }}</textarea>
                                    @if ((string) old('application_id') === (string) $application->id)<x-form.error name="reason" />@endif
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                    <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state :title="'No '.$when.' interviews'" description="Schedule interviews from an applicant's profile." icon="heroicon-o-calendar-days" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$interviews" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
