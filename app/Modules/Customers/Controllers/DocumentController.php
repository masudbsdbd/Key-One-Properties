<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Modules\Customers\Services\CustomerService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DISK = 'local';

    public function index(Customer $customer): JsonResponse
    {
        Gate::authorize('manageDocuments', $customer);

        return Ajax::success(null, ['html' => view('customers.documents._list', [
            'customer' => $customer,
            'documents' => $customer->documents()->with('uploader:id,name')->get(),
        ])->render()]);
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('manageDocuments', $customer);
        abort_if($customer->trashed(), 403);

        $max = config('keyone.uploads.document_max_kb');
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(CustomerDocument::CATEGORIES))],
            'file' => ['required', 'file', 'mimes:'.implode(',', config('keyone.uploads.document_mimes')), 'max:'.$max],
        ], ['file.max' => 'The file must be '.($max / 1024).' MB or smaller.']);

        $file = $request->file('file');
        $customer->documents()->create([
            'category' => $data['category'],
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'disk' => self::DISK,
            'path' => $file->storeAs('customers/'.$customer->public_id, Str::random(40).'.'.$file->guessExtension(), self::DISK),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        CustomerService::log($customer, $request->user(), 'document_uploaded', ['category' => CustomerDocument::CATEGORIES[$data['category']]]);

        return Ajax::success('Document uploaded.');
    }

    public function download(Request $request, Customer $customer, CustomerDocument $document): StreamedResponse
    {
        Gate::authorize('manageDocuments', $customer);
        abort_unless($document->customer_id === $customer->id, 404);

        CustomerService::log($customer, $request->user(), 'document_downloaded', ['document' => $document->original_name]);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, Customer $customer, CustomerDocument $document): JsonResponse
    {
        Gate::authorize('manageDocuments', $customer);
        abort_unless($document->customer_id === $customer->id, 404);

        $document->delete();
        Storage::disk($document->disk)->delete($document->path);
        CustomerService::log($customer, $request->user(), 'document_deleted', ['document' => $document->original_name]);

        return Ajax::success('Document deleted.');
    }
}
