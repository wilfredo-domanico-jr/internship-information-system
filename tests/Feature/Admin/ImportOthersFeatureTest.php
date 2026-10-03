<?php

use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Support\ImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function uploadRows(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'upload.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

it('imports advisers through the UI', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'advisers'), ['file' => uploadRows([ImportColumns::ADVISERS, ['Elsie', '', 'Isip', 'elsie@example.com', '0918']])])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('success');

    expect(User::where('email', 'elsie@example.com')->value('role'))->toBe(Role::Adviser);
});

it('imports classes through the UI', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'classes'), ['file' => uploadRows([ImportColumns::CLASSES, ImportColumns::EXAMPLES['classes']])])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('error'); // example adviser member no does not exist

    expect(ClassSection::count())->toBe(0);

    $row = ImportColumns::EXAMPLES['classes'];
    $row[7] = '';
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'classes'), ['file' => uploadRows([ImportColumns::CLASSES, $row])])
        ->assertSessionHas('success');
    expect(ClassSection::where('course_code', 'CC101')->exists())->toBeTrue();
});

it('does not mention credential emails when classes are imported', function () {
    $row = ImportColumns::EXAMPLES['classes'];
    $row[7] = '';
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'classes'), ['file' => uploadRows([ImportColumns::CLASSES, $row])]);

    expect(session('success'))->toBe('1 classes imported.');
});

it('flashes an error instead of a 500 for a corrupt spreadsheet', function () {
    $file = UploadedFile::fake()->createWithContent('bad.xlsx', 'not really a spreadsheet');

    $response = $this->actingAs($this->admin)->post(route('admin.imports.store', 'advisers'), ['file' => $file]);

    $response->assertRedirect(route('admin.imports.index'))->assertSessionHas('error');
});
