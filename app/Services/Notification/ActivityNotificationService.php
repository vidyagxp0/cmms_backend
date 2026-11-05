<?php

namespace App\Services\Notification;

use App\Mail\ActivityNotification;
use App\Mail\StageActionMail;
use App\Models\Activity;
use App\Models\RecordNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ActivityNotificationService
{
    private const COMPANY_NAME = 'Shilpa Medicare Pvt Ltd';

    /* activity id => role that has to act on the stage the activity moves the record to */
    private const ACTIVITY_ROLES = [
        2 => 'HOD/Designee',
        3 => 'Checker/Reviewer',
        4 => 'QA Approver',
        10 => 'HOD/Designee',
        11 => 'Engineering Department Reviewer',
        12 => 'QA Approver',
        18 => 'Engineering Department Role User',
        19 => 'QA Approver',
        24 => 'HOD/Designee',
        25 => 'QA Approver',
        50 => 'HOD/Designee',
        51 => 'User/Checker',
        52 => 'QA Approver',
        70 => 'HOD/Designee',
        71 => 'QA Approver',
        76 => 'HOD/Designee',
        77 => 'QA Approver',
        82 => 'HOD/Designee',
        83 => 'QA Approver',
    ];

    /* call after a stage move is committed: closes the work of the stage that was left, then notifies the next role */
    public static function notify(Model $record, Activity $activity, ?string $comment = null): void
    {
        try {
            $activity->loadMissing(['fromStage', 'toStage']);
            $performedBy = Auth::user();
            self::completePending($record, $activity->fromStage?->id, $performedBy);
            $role = self::ACTIVITY_ROLES[$activity->id] ?? null;
            if ($role === null || !$activity->toStage) {
                return;
            }
            $users = User::where('is_active', true)
                ->whereNotNull('email')
                ->whereHas('roles', fn ($query) => $query->where('name', $role))
                ->get();
            foreach ($users as $user) {
                self::notifyUser($user, $role, $record, $activity, $performedBy, $comment);
            }
        } catch (\Throwable $e) {
            \Log::error('ACTIVITY NOTIFICATION ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /* the record left a stage, so every pending notification of that stage is completed */
    private static function completePending(Model $record, $stageId, $performedBy): void
    {
        if (!$stageId) {
            return;
        }
        RecordNotification::pending()
            ->where('record_type', $record->getMorphClass())
            ->where('record_id', $record->getKey())
            ->where('stage_id', $stageId)
            ->update([
                'status' => RecordNotification::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $performedBy?->id,
            ]);
    }

    private static function notifyUser(User $user, string $role, Model $record, Activity $activity, $performedBy, ?string $comment): void
    {
        $details = self::buildDetails($user, $role, $record, $activity, $performedBy, $comment);
        $notification = RecordNotification::firstOrCreate(
            [
                'user_id' => $user->id,
                'record_type' => $record->getMorphClass(),
                'record_id' => $record->getKey(),
                'stage_id' => $activity->toStage->id,
                'status' => RecordNotification::STATUS_PENDING,
            ],
            [
                'process_id' => $record->getAttributes()['process_id'] ?? null,
                'activity_id' => $activity->id,
                'role_name' => $role,
                'title' => $details['title'],
                'message' => $details['summary'],
                'performed_by' => $performedBy?->id,
            ]
        );
        if (!$notification->wasRecentlyCreated) {
            return;
        }
        try {
            Mail::to($user->email)->send(new ActivityNotification($details));
            $notification->update(['mailed_at' => now()]);
        } catch (\Throwable $e) {
            \Log::error('ACTIVITY MAIL ERROR', ['user_id' => $user->id, 'message' => $e->getMessage()]);
        }
    }

    private static function buildDetails(User $user, string $role, Model $record, Activity $activity, $performedBy, ?string $comment): array
    {
        $processName = $record->process?->name ?? 'Process Record';
        $recordNumber = self::resolveRecordNumber($record);
        $fromStage = $activity->fromStage?->name ?? '-';
        $toStage = $activity->toStage->name;
        $actor = $performedBy?->name ?? 'System';
        return [
            'title' => "Action required: {$recordNumber} is now at {$toStage}",
            'summary' => "{$recordNumber} ({$processName}) was moved from {$fromStage} to {$toStage} by {$actor}. Your action is required as {$role}.",
            'companyName' => self::COMPANY_NAME,
            'recipientName' => $user->name,
            'roleName' => $role,
            'processName' => $processName,
            'recordNumber' => $recordNumber,
            'activityName' => $activity->name,
            'fromStage' => $fromStage,
            'toStage' => $toStage,
            'performedBy' => $actor,
            'performedAt' => now()->format('d-m-Y H:i'),
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            'link' => self::buildLink($record, $processName),
        ];
    }

    /* record number from process_data, otherwise the instrument / equipment id, otherwise the id */
    private static function resolveRecordNumber(Model $record): string
    {
        $attributes = $record->getAttributes();
        $processData = $attributes['process_data'] ?? null;
        if (is_string($processData)) {
            $processData = json_decode($processData, true);
        }
        foreach ((array) $processData as $field) {
            if (is_array($field) && ($field['key'] ?? null) === 'recordNumber' && !empty($field['value'])) {
                return (string) $field['value'];
            }
        }
        return (string) ($attributes['instrument_id_no'] ?? $attributes['equipment_id_no'] ?? '#' . $record->getKey());
    }

    /* frontend panel link, left out when FRONTEND_URL is not configured */
    private static function buildLink(Model $record, string $processName): ?string
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');
        return $baseUrl === '' ? null : $baseUrl . '/user/' . Str::slug($processName) . '-panel/' . $record->getKey();
    }
}