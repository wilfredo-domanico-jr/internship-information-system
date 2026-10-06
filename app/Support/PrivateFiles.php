<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\ClassResource;
use App\Models\ClassSubmission;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use Illuminate\Database\Eloquent\Model;

class PrivateFiles
{
    /**
     * kind => [model class, attribute holding the path, policy ability checked against the record].
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    public static function registry(): array
    {
        return [
            'company-permit' => [Company::class, 'permit_path', 'viewDocuments'],
            'company-moa' => [Company::class, 'moa_path', 'viewDocuments'],
            'class-submission' => [ClassSubmission::class, 'file_path', 'view'],
            'class-resource' => [ClassResource::class, 'file_path', 'view'],
            'application-resume' => [Application::class, 'resume_path', 'view'],
            'application-endorsement' => [Application::class, 'endorsement_path', 'view'],
            'dtr' => [Dtr::class, 'file_path', 'view'],
            'document-request' => [DocumentRequest::class, 'file_path', 'view'],
            'certificate' => [Certificate::class, 'file_path', 'view'],
        ];
    }

    /** @return array{0: class-string<Model>, 1: string, 2: string} */
    public static function resolve(string $kind): array
    {
        return static::registry()[$kind] ?? abort(404);
    }
}
