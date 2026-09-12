<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Models\Process;
use App\Models\ProcessRecord;
use Auth;
use File;
use Illuminate\Http\Request;
use Str;

class ProcessRecordService
{
    private const UPLOAD_DIRECTORY = 'uploads/recordAttachment';

    /* attachment field keys mapped to their audit-friendly names */
    private const ATTACHMENT_FIELD_NAMES = [
        'attachment' => 'Attachment',
        'hod_review_attachment' => 'HOD Review Comment',
        'user_dept_review_attachment' => 'User Department Review',
        'qa_review_attachment' => 'QA Review',
        'cancellation_attachment' => 'Cancellation',
    ];

    public static function getCalibrationPlannerRecords(Request $request)
    {
        return self::getRecordsByProcessName($request, 'Calibration Planner');
    }

    public static function getCalibrationManagementRecords(Request $request)
    {
        return self::getRecordsByProcessName($request, 'Calibration Management');
    }

    public static function getPreventiveMaintenancePlannerRecords(Request $request)
    {
        return self::getRecordsByProcessName($request, 'Preventive Maintenance Planner');
    }

    public static function getPreventiveMaintenanceRecords(Request $request)
    {
        return self::getRecordsByProcessName($request, 'Preventive Maintenance');
    }

    /* fetch paginated records for a process, with search + date range filters */
    private static function getRecordsByProcessName(Request $request, string $processName)
    {
        try {
            $records = ProcessRecord::with(['process', 'department', 'initiator', 'stage'])
                ->whereHas('process', fn($query) => $query->where('name', $processName))
                ->when($request->search, function ($query) use ($request) {
                    $search = $request->search;

                    /* match on short description or any related name field */
                    $query->where(function ($query) use ($search) {
                        $query->where('short_description', 'like', "%{$search}%")
                            ->orWhereHas('process', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('initiator', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('department', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when($request->from_date, fn($query) => $query->whereDate('initiation_date', '>=', $request->from_date))
                ->when($request->to_date, fn($query) => $query->whereDate('initiation_date', '<=', $request->to_date))
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 10);

            return ResponseHelper::success($records, 'Process records fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* build a record number like "CP/24/003" from the process name, year, and yearly count */
    public static function getGeneratedRecordNumber($processId)
    {
        $process = Process::findOrFail($processId);
        $words = explode(' ', trim($process->name));

        /* multi-word names use initials (e.g. "Calibration Planner" -> "CP"), single-word uses first 3 letters */
        if (count($words) > 1) {
            $prefix = strtoupper(implode('', array_map(fn($w) => $w !== '' ? substr($w, 0, 1) : '', $words)));
        } else {
            $prefix = strtoupper(substr($process->name, 0, 3));
        }

        $count = ProcessRecord::where('process_id', $processId)->whereYear('created_at', date('Y'))->count();
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}/" . date('y') . "/{$sequence}";
    }

    /* generate and return a new record number */
    public static function generateRecordNumber($processId)
    {
        try {
            return ResponseHelper::success(
                ['record_number' => self::getGeneratedRecordNumber($processId)],
                'Record number generated successfully.'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Process not found.', 404);
        } catch (\Exception $e) {
            return ResponseHelper::error('Failed to generate record number.', 500);
        }
    }

    /* check whether the logged-in user's role can act on a record's current stage */
    public static function checkRecordPermission($id)
    {
        try {
            $user = Auth::user();
            if (!$user) return ResponseHelper::error('Unauthenticated.', 401);

            $record = ProcessRecord::with(['stage', 'process'])->findOrFail($id);
            $roles = $user->roles()->pluck('name')->toArray();

            /* stage => required role, per process id */
            $stageRoles = [
                1 => [ // Calibration Planner
                    'Opened' => 'Initiator',
                    'Reviewed By HOD/Designee (Engineering)' => 'HOD/Designee',
                    'Reviewed By User Department' => 'User Department Role',
                    'Pending QA Approval' => 'QA Approver',
                ],
                2 => [ // Preventive Maintenance Planner
                    'Opened' => 'Initiator',
                    'Pending HOD/Designee Review' => 'HOD/Designee',
                    'Pending QA Review' => 'QA Reviewer',
                    'Pending QA Approval' => 'QA Approver',
                ],
                4 => [ // Preventive Maintenance
                    'Opened' => 'Initiator',
                    'Engineering Department Review' => 'HOD/Designee',
                    'Pending QA Approval' => 'QA Approver',
                ],
                5 => [ // Calibration Management
                    'Opened' => 'Initiator',
                    'Reviewed By HOD/Designee' => 'HOD/Designee',
                    'Verified By QA' => 'QA Approver',
                ],
            ];

            $stageName = $record->stage?->name;
            $allowedRole = $stageRoles[$record->process_id][$stageName] ?? null;
            $canPerformAction = $allowedRole ? in_array($allowedRole, $roles) : false;

            return ResponseHelper::success([
                'record_id' => $record->id,
                'process' => ['id' => $record->process?->id, 'name' => $record->process?->name],
                'stage' => ['id' => $record->stage?->id, 'name' => $stageName],
                'user' => ['id' => $user->id, 'name' => $user->name, 'roles' => $roles],
                'permission' => ['allowed_role' => $allowedRole, 'can_perform_action' => $canPerformAction],
            ], 'Record permission checked successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* upload one or more attachments for a record, replacing the old file for single-file fields */
    public static function uploadAttachments(Request $request, $recordId)
    {
        try {
            $processRecord = ProcessRecord::findOrFail($recordId);
            $attachmentField = $request->input('attachment_field');
            $type = $request->input('Type');

            if (!$attachmentField) return ResponseHelper::error('Attachment field is required.', 422);
            if (!in_array($type, ['single-file', 'multiple-file'])) return ResponseHelper::error('Invalid attachment type.', 422);

            $files = self::collectUploadedFiles($request);
            if (empty($files)) return ResponseHelper::error('No attachment file was provided.', 422);

            $uploadDirectory = public_path(self::UPLOAD_DIRECTORY);
            if (!File::exists($uploadDirectory)) File::makeDirectory($uploadDirectory, 0755, true);

            $existingAttachments = self::decodeAttachments($processRecord->attachments);
            $oldAttachmentsToDelete = [];

            /* single-file fields replace whatever was previously stored under that field */
            if ($type === 'single-file') {
                $oldAttachmentsToDelete = array_values(array_filter(
                    $existingAttachments,
                    fn($a) => is_array($a) && ($a['attachment_field'] ?? null) === $attachmentField
                ));

                $existingAttachments = array_values(array_filter(
                    $existingAttachments,
                    fn($a) => !(is_array($a) && ($a['attachment_field'] ?? null) === $attachmentField)
                ));
            }

            $newAttachments = self::storeUploadedFiles($files, $attachmentField, $uploadDirectory);
            if (empty($newAttachments)) return ResponseHelper::error('No valid attachment files were uploaded.', 422);

            $allAttachments = array_merge($existingAttachments, $newAttachments);
            $processRecord->update(['attachments' => $allAttachments]);

            if (!empty($oldAttachmentsToDelete)) {
                UserAuditHelper::log(
                    'Process Record', 'Deleted', 'Attachment deleted successfully.', $processRecord->id,
                    ['attachments' => self::formatAuditAttachments($oldAttachmentsToDelete)], null, ProcessRecord::class
                );
            }

            if (!empty($newAttachments)) {
                UserAuditHelper::log(
                    'Process Record', 'Created', 'Attachment uploaded successfully.', $processRecord->id,
                    null, ['attachments' => self::formatAuditAttachments($newAttachments)], ProcessRecord::class
                );
            }

            if ($type === 'single-file') {
                foreach ($oldAttachmentsToDelete as $attachment) {
                    if (empty($attachment['file_name'])) continue;

                    $oldFilePath = $uploadDirectory . DIRECTORY_SEPARATOR . $attachment['file_name'];
                    if (File::exists($oldFilePath)) File::delete($oldFilePath);
                }
            }

            return ResponseHelper::success([
                'record_id' => $processRecord->id,
                'attachment_field' => $attachmentField,
                'type' => $type,
                'uploaded_count' => count($newAttachments),
                'total_count' => count($allAttachments),
                'attachments' => $allAttachments,
                'new_attachments' => $newAttachments,
            ], 'Attachments uploaded successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* gather valid uploaded files from any of the accepted input names */
    private static function collectUploadedFiles(Request $request): array
    {
        $files = [];

        foreach (['file', 'file[]', 'files', 'files[]'] as $inputName) {
            if (!$request->hasFile($inputName)) continue;

            $inputFiles = $request->file($inputName);
            $inputFiles = is_array($inputFiles) ? $inputFiles : [$inputFiles];

            foreach ($inputFiles as $file) {
                if ($file && $file->isValid()) $files[] = $file;
            }
        }

        return $files;
    }

    /* decode a record's stored attachments column into an array */
    private static function decodeAttachments($attachments): array
    {
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        return is_array($attachments) ? $attachments : [];
    }

    /* move uploaded files into the upload directory and build their attachment records */
    private static function storeUploadedFiles(array $files, string $attachmentField, string $uploadDirectory): array
    {
        $newAttachments = [];

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) continue;

            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $file->move($uploadDirectory, $fileName);

            $relativePath = self::UPLOAD_DIRECTORY . '/' . $fileName;

            $newAttachments[] = [
                'attachment_field' => $attachmentField,
                'name' => $file->getClientOriginalName(),
                'file_name' => $fileName,
                'path' => $relativePath,
                'url' => asset($relativePath),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $newAttachments;
    }

    /* reduce attachments to [field, name, url] for audit display */
    private static function formatAuditAttachments(array $attachments): array
    {
        return array_values(array_map(function ($attachment) {
            $field = $attachment['attachment_field'] ?? null;

            return [
                'field' => self::ATTACHMENT_FIELD_NAMES[$field] ?? $field,
                'name' => $attachment['name'] ?? null,
                'url' => $attachment['url'] ?? null,
            ];
        }, array_filter($attachments, 'is_array')));
    }
}