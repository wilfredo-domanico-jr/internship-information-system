<x-layouts.app title="Imports">
    <x-page-header title="Excel imports" subtitle="Download a template, fill one row per record, then upload it. Imports are all-or-nothing: if any row has an error, nothing is created." />

    @if ($result)
        <x-card :title="$result['errors'] ? 'Import failed' : 'Import complete'" class="{{ $result['errors'] ? 'border-rose-200 dark:border-rose-900' : 'border-emerald-200 dark:border-emerald-900' }}">
            @if ($result['errors'])
                <p class="text-sm text-stone-600 dark:text-stone-400">Fix the rows below and upload the file again. No records were created.</p>
                <ul class="mt-3 max-h-72 list-inside list-disc space-y-1 overflow-y-auto text-sm text-rose-700 dark:text-rose-300">
                    @foreach ($result['errors'] as $line)<li>{{ $line }}</li>@endforeach
                </ul>
            @else
                <p class="text-sm">{{ $result['created'] }} record(s) created. Credential emails are being sent in the background.</p>
            @endif
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($types as $type => $meta)
            <x-card :title="$meta['title']" :subtitle="$meta['blurb']">
                <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Columns</p>
                <p class="mt-1 font-mono text-xs text-stone-600 dark:text-stone-400">{{ implode(', ', $meta['columns']) }}</p>
                <a href="{{ route('admin.imports.template', $type) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-arrow-down-tray class="size-4" /> Download template</a>
                @if (Route::has('admin.imports.store'))
                    <form method="POST" action="{{ route('admin.imports.store', $type) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-stone-200/80 pt-5 dark:border-stone-800">
                        @csrf
                        <x-form.file name="file" label="Spreadsheet (.xlsx)" accept=".xlsx,.xls" required />
                        <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Import {{ strtolower($meta['title']) }}</x-button>
                    </form>
                @endif
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
