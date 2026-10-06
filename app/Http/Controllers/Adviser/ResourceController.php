<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\AddClassResource;
use App\Actions\DeleteClassResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ResourceRequest;
use App\Models\ClassResource;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResourceController extends Controller
{
    public function store(ResourceRequest $request, ClassSection $classSection, AddClassResource $add): RedirectResponse
    {
        $resource = $add($classSection, $request->user(), $request->validated('title'), $request->file('file'));

        return redirect()->route('adviser.classes.documents', $classSection)->with('success', "“{$resource->title}” shared with the class.");
    }

    public function destroy(ClassResource $resource, DeleteClassResource $delete): RedirectResponse
    {
        Gate::authorize('delete', $resource);

        $section = $resource->classSection;
        $delete($resource);

        return redirect()->route('adviser.classes.documents', $section)->with('success', "“{$resource->title}” removed.");
    }
}
