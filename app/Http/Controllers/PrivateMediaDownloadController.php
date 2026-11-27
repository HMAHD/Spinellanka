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
            if ($conditionAttachment->processing_status === 'purged_retention' || $conditionAttachment->file_path === 'purged') {
                abort(410, 'This attachment has been permanently purged under the statutory privacy retention policy.');
            }

            if ($conditionAttachment->processing_status !== 'clean') {
                abort(403, 'Attachment is not approved for download or is still undergoing quarantine.');
            }

            if (! $user->can(Permission::BookingView->value) && ! $user->hasPermissionTo(Permission::BookingView->value)) {
                abort(403, 'Unauthorized access to condition attachment.');
            }

            $extension = pathinfo($conditionAttachment->original_filename, PATHINFO_EXTENSION) ?: 'bin';
            $safeFilename = sprintf('attachment_%s.%s', $conditionAttachment->file_uuid, $extension);

            return $this->mediaPipeline->createSafeDownloadResponse(
                relativePath: $conditionAttachment->file_path,
                downloadFilename: $safeFilename,
                actor: $user,
                contextSummary: "Staff {$user->name} viewed condition attachment {$conditionAttachment->file_uuid}"
            );
        }

        // 2. Try finding as incident attachment
        /** @var IncidentAttachment|null $incidentAttachment */
        $incidentAttachment = IncidentAttachment::where('file_uuid', $uuid)->first();
        if ($incidentAttachment !== null) {
            if ($incidentAttachment->processing_status === 'purged_retention' || $incidentAttachment->file_path === 'purged') {
                abort(410, 'This attachment has been permanently purged under the statutory privacy retention policy.');
            }

            if ($incidentAttachment->processing_status !== 'clean') {
                abort(403, 'Attachment is not approved for download or is still undergoing quarantine.');
            }

            if (! $user->can(Permission::IncidentView->value) && ! $user->hasPermissionTo(Permission::IncidentView->value)) {
                abort(403, 'Unauthorized access to incident evidence.');
            }

            $extension = pathinfo($incidentAttachment->original_filename, PATHINFO_EXTENSION) ?: 'bin';
            $safeFilename = sprintf('attachment_%s.%s', $incidentAttachment->file_uuid, $extension);

            return $this->mediaPipeline->createSafeDownloadResponse(
                relativePath: $incidentAttachment->file_path,
                downloadFilename: $safeFilename,
                actor: $user,
                contextSummary: "Staff {$user->name} viewed incident evidence {$incidentAttachment->file_uuid}"
            );
        }

        abort(404, 'Attachment not found.');
    }
}
