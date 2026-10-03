<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ImportAdvisers;
use App\Actions\ImportClasses;
use App\Actions\ImportInterns;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportRequest;
use App\Services\Excel\SpreadsheetReader;
use App\Services\Excel\TemplateBuilder;
use App\Support\ImportColumns;
use App\Support\ImportResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.index', [
            'types' => [
                'interns' => ['title' => 'Interns', 'blurb' => 'Creates intern accounts, enrolls them in their class by section name, and emails each one a temporary password.', 'columns' => ImportColumns::INTERNS],
                'advisers' => ['title' => 'Advisers', 'blurb' => 'Creates adviser accounts and emails each one a temporary password.', 'columns' => ImportColumns::ADVISERS],
                'classes' => ['title' => 'Classes', 'blurb' => 'Creates class sections with a generated join code. Optionally assigns an adviser by member number.', 'columns' => ImportColumns::CLASSES],
            ],
            'result' => session('import_result'),
        ]);
    }

    public function store(ImportRequest $request, string $type, SpreadsheetReader $reader): RedirectResponse
    {
        abort_unless(in_array($type, ImportColumns::TYPES, true), 404);

        try {
            $rows = $reader->read($request->file('file')->getRealPath());
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception|Exception $e) {
            return redirect()->route('admin.imports.index')
                ->with('error', 'That file could not be read as a spreadsheet. Please upload the .xlsx template.');
        }

        if ($rows->isEmpty()) {
            // Garbage that PhpSpreadsheet sniffs as CSV/HTML yields no data rows rather than an exception.
            return redirect()->route('admin.imports.index')
                ->with('error', 'No data rows were found below the header. Fill in at least one row of the .xlsx template and upload it again.');
        }

        /** @var ImportResult $result */
        $result = match ($type) {
            'interns' => app(ImportInterns::class)($rows),
            'advisers' => app(ImportAdvisers::class)($rows),
            'classes' => app(ImportClasses::class)($rows),
            default => abort(404),
        };

        return redirect()->route('admin.imports.index')
            ->with('import_result', $result->toArray())
            ->with($result->failed() ? 'error' : 'success', $result->failed()
                ? 'The import was not applied because some rows have errors.'
                : "{$result->created} {$type} imported.".($type !== 'classes' ? ' Credential emails are queued.' : ''));
    }

    public function template(string $type, TemplateBuilder $templates): BinaryFileResponse
    {
        abort_unless(in_array($type, ImportColumns::TYPES, true), 404);

        $path = $templates->build(ImportColumns::headersFor($type), ImportColumns::EXAMPLES[$type], ucfirst($type));

        return response()->download($path, "wiis-{$type}-template.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
