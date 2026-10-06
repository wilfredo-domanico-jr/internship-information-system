<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="course_code" label="Course code" :value="$class?->course_code" required placeholder="CC101" />
    <x-form.input name="section" label="Section" :value="$class?->section" required placeholder="SBIT-4C" />
    <x-form.input name="subject" label="Subject" :value="$class?->subject" required placeholder="Practicum" class="sm:col-span-2" />
    <x-form.select name="day" label="Day" :options="array_combine($days, $days)" :value="$class?->day" required placeholder="Choose a day" />
    <x-form.input name="school_year" label="School year" :value="$class?->school_year" required placeholder="2025-2026" hint="Format: 2025-2026" />
    <x-form.input name="starts_at" label="Starts at" type="time" :value="$class ? substr($class->starts_at, 0, 5) : null" required />
    <x-form.input name="ends_at" label="Ends at" type="time" :value="$class ? substr($class->ends_at, 0, 5) : null" required />
</div>
