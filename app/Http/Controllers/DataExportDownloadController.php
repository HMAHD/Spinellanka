<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\Services\DataExportService;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportDownloadController extends Controller
{
    public function __construct(
        protected DataExportService $exportService,
    ) {}

    public function download(Request $request, string $uuid): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var DataExport $export */
        $export = DataExport::where('export_uuid', $uuid)->firstOrFail();

        if ($export->isExpired() || $export->file_path === 'purged') {
            abort(410, 'This data export has expired and been purged.');
        }

        return $this->exportService->downloadExport($export, $user);
    }
}
