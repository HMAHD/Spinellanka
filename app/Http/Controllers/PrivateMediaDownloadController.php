<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Permission;
use App\Domain\Operations\Services\PrivateMediaPipelineService;
use App\Models\ConditionAttachment;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateMediaDownloadController extends Controller
{
    public function __construct(
        protected PrivateMediaPipelineService $mediaPipeline,
    ) {}

    public function download(Request $request, string $uuid): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Try finding as condition attachment
        /** @var ConditionAttachment|null $conditionAttachment */
        $conditionAttachment = ConditionAttachment::where('file_uuid', $uuid)->first();
        if ($conditionAttachment !== null) {
            if (! $user->can(Permission::BookingView->value) && ! $user->hasPermissionTo(Permission::BookingView->value)) {
                abort(403, 'Unauthorized access to condition attachment.');
            }

            return $this->mediaPipeline->createSafeDownloadResponse(
                relativePath: $conditionAttachment->file_path,
                downloadFilename: $conditionAttachment->original_filename,
                actor: $user,
                contextSummary: "Staff {$user->name} viewed condition attachment {$conditionAttachment->file_uuid}"
            );
        }

        // 2. Try finding as incident attachment
        /** @var IncidentAttachment|null $incidentAttachment */
        $incidentAttachment = IncidentAttachment::where('file_uuid', $uuid)->first();
        if ($incidentAttachment !== null) {
            if (! $user->can(Permission::IncidentView->value) && ! $user->hasPermissionTo(Permission::IncidentView->value)) {
                abort(403, 'Unauthorized access to incident evidence.');
            }

            return $this->mediaPipeline->createSafeDownloadResponse(
                relativePath: $incidentAttachment->file_path,
                downloadFilename: $incidentAttachment->original_filename,
                actor: $user,
                contextSummary: "Staff {$user->name} viewed incident evidence {$incidentAttachment->file_uuid}"
            );
        }

        abort(404, 'Attachment not found.');
    }
}
