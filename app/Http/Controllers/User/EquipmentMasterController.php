<?php

namespace App\Http\Controllers\User;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\InstrumentMaster;
use App\Services\User\EquipmentMasterService;
use App\Services\UserReport\InstrumentReportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\HeaderUtils;

class EquipmentMasterController extends Controller
{
    /* Instrument attachment upload directory */
    const INSTRUMENT_UPLOAD_DIRECTORY = 'uploads/instrument-attachments';

    /* allowed Instrument attachment columns */
    const INSTRUMENT_ATTACHMENT_FIELDS = [
        'certificate_attachment',
    ];

    /* Instrument listing function */
    public function index(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::getEquipments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* Instrument details function */
    public function show($id)
    {
        try {
            return EquipmentMasterService::getEquipmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* store Instrument function */
    public function store(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::storeEquipment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Instrument.',
            ], 500);
        }
    }

    /* update Instrument function */
    public function update(EquipmentMasterRequest $request,$id)
    {
        try {
            return EquipmentMasterService::updateEquipment(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Instrument.',
            ], 500);
        }
    }


    public function getAllEquipments()
    {
        try {
            return EquipmentMasterService::getAllEquipments();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* Instrument checklist function */
    public function getChecklistByFrequency($equipmentId, $frequency)
    {
        try {
            return EquipmentMasterService::getChecklistByFrequency($equipmentId, $frequency);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument checklist.',
            ], 500);
        }
    }

    /* Instrument audit trail function */
    public function getInstrumentAuditTrail(Request $request, $id)
    {
        try {
            return EquipmentMasterService::getInstrumentAuditTrail($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* move Instrument Master process record stage */
    public function moveStage(RecordActivityRequest $request, $id)
    {
        try {
            return EquipmentMasterService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Instrument Master stage.',
            ], 500);
        }
    }

    /* user permission */
    public function checkInstrumentRecordPermission($id) 
    {
        try {
            return EquipmentMasterService::checkInstrumentRecordPermission(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data.',
            ], 500);
        }
    }

    /* instrument single report */
    public function instrumentSingleReport($id) 
    {
        try {
            return InstrumentReportService::generateReport(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report.',
            ], 500);
        }
    }

    /* upload Instrument attachment function */
    public function uploadAttachments(Request $request, $id)
    {
        $uploadDirectory = public_path(self::INSTRUMENT_UPLOAD_DIRECTORY);
        $newAttachments = [];

        try {
            $instrument = InstrumentMaster::findOrFail($id);

            $attachmentField = $request->input('attachment_field');
            $label = $request->input('label');
            $type = $request->input('Type');

            if (!$attachmentField) {
                return ResponseHelper::error('Attachment field is required.', 422);
            }

            if (!in_array($attachmentField, self::INSTRUMENT_ATTACHMENT_FIELDS, true)) {
                return ResponseHelper::error('Invalid attachment field.', 422);
            }

            if (!in_array($type, ['single-file', 'multiple-file'], true)) {
                return ResponseHelper::error('Invalid attachment type.', 422);
            }

            $files = $this->collectInstrumentFiles($request);

            if (empty($files)) {
                return ResponseHelper::error('No attachment file was provided.', 422);
            }

            if (!File::exists($uploadDirectory)) {
                File::makeDirectory($uploadDirectory, 0755, true);
            }

            $existingAttachments = $this->decodeInstrumentAttachments($instrument->{$attachmentField});
            $oldAttachmentsToDelete = [];

            /* single-file fields replace whatever was previously stored */
            if ($type === 'single-file') {
                $oldAttachmentsToDelete = $existingAttachments;
                $existingAttachments = [];
            }

            $newAttachments = $this->storeInstrumentFiles(
                $files,
                $attachmentField,
                $label,
                $uploadDirectory
            );

            if (empty($newAttachments)) {
                return ResponseHelper::error('No valid attachment files were uploaded.', 422);
            }

            /* single-file replaces, multiple-file appends */
            $allAttachments = array_values(array_merge($existingAttachments, $newAttachments));

            $instrument->update([
                $attachmentField => $allAttachments,
            ]);

            /* Instrument attachment audit */
            if (!empty($oldAttachmentsToDelete)) {
                UserAuditHelper::log(
                    'Instrument Master',
                    'Deleted',
                    'Attachment deleted successfully.',
                    $instrument->id,
                    ['attachments' => $this->formatInstrumentAuditAttachments($oldAttachmentsToDelete)],
                    null,
                    InstrumentMaster::class
                );
            }

            UserAuditHelper::log(
                'Instrument Master',
                'Created',
                'Attachment uploaded successfully.',
                $instrument->id,
                null,
                ['attachments' => $this->formatInstrumentAuditAttachments($newAttachments)],
                InstrumentMaster::class
            );

            /* delete replaced physical files */
            $this->deleteInstrumentFiles($oldAttachmentsToDelete, $uploadDirectory);

            return ResponseHelper::success([
                'instrument_id' => $instrument->id,
                'attachment_field' => $attachmentField,
                'type' => $type,
                'uploaded_count' => count($newAttachments),
                'total_count' => count($allAttachments),
                'attachments' => $allAttachments,
                'new_attachments' => $newAttachments,
            ], 'Attachments uploaded successfully.');

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Instrument not found.',
            ], 404);

        } catch (\Exception $e) {
            /* remove newly uploaded files if update failed */
            $this->deleteInstrumentFiles($newAttachments, $uploadDirectory);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload Instrument attachment.',
            ], 500);
        }
    }

    /* collect uploaded Instrument files from request */
    private function collectInstrumentFiles(Request $request)
    {
        $files = $request->file('files') ?? $request->file('file') ?? [];

        if (!is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter(
            $files,
            fn($file) => $file && $file->isValid()
        ));
    }

    /* store Instrument files on disk */
    private function storeInstrumentFiles(array $files, $attachmentField, $label, $uploadDirectory)
    {
        $storedAttachments = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();

            $fileName = time() . '_' . uniqid() . ($extension ? '.' . $extension : '');

            $file->move($uploadDirectory, $fileName);

            $storedAttachments[] = [
                'attachment_field' => $attachmentField,
                'label' => $label,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'file_path' => self::INSTRUMENT_UPLOAD_DIRECTORY . '/' . $fileName,
                'file_url' => url(self::INSTRUMENT_UPLOAD_DIRECTORY . '/' . $fileName),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return $storedAttachments;
    }

    /* decode Instrument attachments column */
    private function decodeInstrumentAttachments($attachments)
    {
        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true);
        }

        if (!is_array($attachments)) {
            return [];
        }

        return array_values(array_filter($attachments, 'is_array'));
    }

    /* format Instrument attachments for audit */
    private function formatInstrumentAuditAttachments(array $attachments)
    {
        return array_map(fn($attachment) => [
            'label' => $attachment['label'] ?? null,
            'original_name' => $attachment['original_name'] ?? null,
            'file_name' => $attachment['file_name'] ?? null,
        ], $attachments);
    }

    /* delete Instrument physical files */
    private function deleteInstrumentFiles(array $attachments, $uploadDirectory)
    {
        foreach ($attachments as $attachment) {
            if (empty($attachment['file_name'])) {
                continue;
            }

            $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . basename($attachment['file_name']);

            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }
    }

    /* Instrument certificate attachment function (list + view) */
    public function certificateAttachments(Request $request, $id)
    {
        try {
            $instrument = InstrumentMaster::findOrFail($id);

            $attachments = $this->decodeInstrumentAttachments($instrument->certificate_attachment);

            /* file requested, return the file itself */
            if ($request->filled('file')) {
                $fileName = basename($request->query('file'));

                /* serve only files that belong to this instrument */
                $attachment = collect($attachments)->firstWhere('file_name', $fileName);

                if (!$attachment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attachment not found.',
                    ], 404);
                }

                $filePath = public_path(self::INSTRUMENT_UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $fileName);

                if (!File::exists($filePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attachment file not found on server.',
                    ], 404);
                }

                return response()->file($filePath, [
                    'Content-Type' => File::mimeType($filePath) ?: 'application/octet-stream',
                    'Content-Disposition' => HeaderUtils::makeDisposition(
                        HeaderUtils::DISPOSITION_INLINE,
                        $attachment['original_name'] ?? $fileName,
                        $fileName
                    ),
                ]);
            }

            /* no file requested, return the attachment list */
            $attachments = array_map(fn($attachment) => [
                'file_name' => $attachment['file_name'] ?? null,
                'original_name' => $attachment['original_name'] ?? ($attachment['file_name'] ?? null),
                'label' => $attachment['label'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'file_size' => $attachment['file_size'] ?? null,
                'uploaded_at' => $attachment['uploaded_at'] ?? null,
                'extension' => strtolower(pathinfo($attachment['file_name'] ?? '', PATHINFO_EXTENSION)),
            ], $attachments);

            return ResponseHelper::success([
                'instrument_id' => $instrument->id,
                'total_count' => count($attachments),
                'attachments' => array_values($attachments),
            ], 'Certificate attachments retrieved successfully.');

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Instrument not found.',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load certificate attachments.',
            ], 500);
        }
    }
}