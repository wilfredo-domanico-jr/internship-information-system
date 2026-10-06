<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="title" label="Position title" :value="$posting?->title" required placeholder="Junior Web Developer Intern" class="sm:col-span-2" />
    <x-form.input name="city" label="City" :value="$posting?->city" required placeholder="Quezon City" />
    <x-form.input name="vacancies" label="Vacancies" type="number" min="1" max="100" :value="$posting?->vacancies ?? 1" required />
    <x-form.textarea name="description" label="Description" :value="$posting?->description" required rows="5" class="sm:col-span-2" />
    <x-form.textarea name="responsibilities" label="Responsibilities" :value="$posting?->responsibilities" rows="4" class="sm:col-span-2" hint="Optional. One per line works well." />
    <x-form.input name="closing_date" label="Closing date" type="date" :value="$posting?->closing_date?->toDateString()" hint="Leave blank to keep accepting applications." />
    <x-form.input name="required_hours" label="Required hours" type="number" min="1" max="2000" :value="$posting?->required_hours" hint="Optional. Defaults to the school requirement." />
    <x-form.input name="contact_name" label="Contact name" :value="$posting?->contact_name" required />
    <x-form.input name="contact_position" label="Contact position" :value="$posting?->contact_position" />
    <x-form.input name="contact_phone" label="Contact phone" :value="$posting?->contact_phone" />
</div>
