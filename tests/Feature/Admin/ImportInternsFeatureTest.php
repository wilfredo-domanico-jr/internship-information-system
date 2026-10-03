<?php

use App\Models\ClassSection;
use App\Models\User;
use App\Support\ImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function uploadFromRows(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'interns.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    ClassSection::factory()->create(['section' => 'SBIT-4C']);
});

it('imports a valid spreadsheet and reports the count', function () {
    $file = uploadFromRows([ImportColumns::INTERNS, ['Ana', '', 'Cruz', 'ana@example.com', '0917', 'Male', '22-0001', 'SBIT-4C', '']]);

    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => $file])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('success')->assertSessionHas('import_result.created', 1);

    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.imports.index'))->assertSee('1 record(s) created');
});

it('shows row errors and creates nothing on a bad file', function () {
    $file = uploadFromRows([ImportColumns::INTERNS, ['Ana', '', 'Cruz', 'not-an-email', '', 'Male', '22-0001', 'SBIT-4C', '']]);

    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => $file])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('error');

    expect(User::where('first_name', 'Ana')->exists())->toBeFalse();
    $this->actingAs($this->admin)->get(route('admin.imports.index'))->assertSee('Row 2');
});

it('rejects non-spreadsheet uploads and unknown types', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'payroll'), ['file' => uploadFromRows([['a']])])->assertNotFound();
});

it('rejects a sheet over the row cap before importing anything', function () {
    $rows = [ImportColumns::INTERNS];
    for ($i = 1; $i <= 501; $i++) {
        $rows[] = ['Ana', '', 'Cruz', "ana{$i}@example.com", '', 'Male', "22-{$i}", 'SBIT-4C', ''];
    }

    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => uploadFromRows($rows)])
        ->assertRedirect(route('admin.imports.index'))
        ->assertSessionHas('error', 'This file has 501 rows; imports are limited to 500 rows per upload. Split the file and try again.');

    expect(User::where('first_name', 'Ana')->exists())->toBeFalse();
});
