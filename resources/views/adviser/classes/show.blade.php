<x-layouts.app :title="$class->display_name">
    @include('adviser.classes.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @can('manage', $class)
                <x-card x-data="{ open: {{ $errors->has('body') ? 'true' : 'false' }} }">
                    <button type="button" x-show="!open" @click="open = true; $nextTick(() => $el.parentElement.querySelector('trix-editor')?.focus())"
                            class="flex w-full items-center gap-3 text-left text-sm text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">
                        <x-avatar :user="auth()->user()" size="sm" /> Announce something to your class…
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('adviser.announcements.store', $class) }}" class="space-y-4">
                        @csrf
                        <x-form.editor name="body" placeholder="Announce something to your class…" />
                        <div class="flex items-center justify-end gap-2">
                            <x-button type="button" variant="ghost" @click="open = false">Cancel</x-button>
                            <x-button icon="heroicon-o-paper-airplane">Post</x-button>
                        </div>
                    </form>
                </x-card>
            @endcan
            @forelse ($announcements as $announcement)
                @include('classroom.announcement', ['announcement' => $announcement])
            @empty
                <x-card><x-empty-state title="No announcements yet" description="Posts to the class stream will appear here." icon="heroicon-o-megaphone" /></x-card>
            @endforelse
            <x-pagination :paginator="$announcements" />
        </div>

        <div class="space-y-4">
            <x-card title="About this class">
                <x-detail-list class="sm:grid-cols-1" :items="[
                    'Subject' => $class->subject,
                    'Schedule' => $class->schedule_label,
                    'School year' => $class->school_year,
                    'Adviser' => $class->adviser?->name ?? 'No adviser',
                    'Interns' => $class->intern_profiles_count,
                ]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
