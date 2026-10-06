<x-layouts.app title="Certificates">
    <x-page-header title="Certificates" subtitle="Certificates of completion issued by the companies you interned with." />

    @if ($certificates->isEmpty())
        <x-card><x-empty-state title="No certificates yet" :description="'A company can issue one once you have rendered '.$minimum.' hours with them.'" icon="heroicon-o-trophy" /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($certificates as $certificate)
                <x-card class="flex flex-col">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10"><x-heroicon-o-trophy class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $certificate->placement->company->name }}</p>
                            <p class="text-sm text-stone-500">Issued {{ $certificate->issued_at->format('M j, Y') }} · {{ $certificate->hours_at_issue }} h</p>
                        </div>
                    </div>
                    <x-button variant="secondary" :href="route('files.show', ['certificate', $certificate])" target="_blank" icon="heroicon-o-arrow-down-tray" class="mt-4 w-full">Download PDF</x-button>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
