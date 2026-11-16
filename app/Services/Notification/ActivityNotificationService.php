<?php

namespace App\Services\Notification;

use App\Mail\ActivityNotification;
use App\Models\Activity;
use App\Models\RecordNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ActivityNotificationService
{
    private const COMPANY_NAME = 'Shilpa Medicare Pvt Ltd';

    private const INITIATOR = 'Initiator';
    private const HOD = 'HOD/Designee';
    private const USER_DEPARTMENT = 'User Department Reviewer';
    private const ENGINEERING = 'Engineering Department Reviewer';
    private const QA = 'QA Approver';

    private const KIND_CREATED = 'created';
    private const KIND_ACTION = 'action';
    private const KIND_UPDATE = 'update';
    private const KIND_CANCEL = 'cancel';

    /* activity id => roles to notify; a list containing the initiator is a final activity (information only) */
    private const ACTIVITY_ROLES = [
        2 => [self::HOD],
        3 => [self::USER_DEPARTMENT],
        4 => [self::QA],
        5 => [self::INITIATOR, self::HOD, self::USER_DEPARTMENT, self::QA],
        10 => [self::HOD],
        11 => [self::USER_DEPARTMENT],
        12 => [self::QA],
        13 => [self::INITIATOR, self::HOD, self::USER_DEPARTMENT, self::QA],
        18 => [self::USER_DEPARTMENT],
        19 => [self::ENGINEERING],
        20 => [self::QA],
        21 => [self::INITIATOR, self::USER_DEPARTMENT, self::ENGINEERING, self::QA],
        26 => [self::HOD],
        27 => [self::QA],
        28 => [self::INITIATOR, self::HOD, self::QA],
        32 => [self::HOD],
        33 => [self::QA],
        34 => [self::INITIATOR, self::HOD, self::QA],
        38 => [self::HOD],
        39 => [self::QA],
        40 => [self::INITIATOR, self::HOD, self::QA],
        44 => [self::HOD],
        45 => [self::USER_DEPARTMENT],
        46 => [self::QA],
        47 => [self::INITIATOR, self::HOD, self::USER_DEPARTMENT, self::QA],
        52 => [self::HOD],
        53 => [self::USER_DEPARTMENT],
        54 => [self::QA],
        55 => [self::INITIATOR, self::HOD, self::USER_DEPARTMENT, self::QA],
        60 => [self::HOD],
        61 => [self::QA],
        62 => [self::INITIATOR, self::HOD, self::QA],
        66 => [self::HOD],
        67 => [self::QA],
        68 => [self::INITIATOR, self::HOD, self::QA],
        72 => [self::HOD],
        73 => [self::QA],
        74 => [self::INITIATOR, self::HOD, self::QA],
        78 => [self::HOD],
        79 => [self::QA],
        80 => [self::INITIATOR, self::HOD, self::QA],
        84 => [self::HOD],
        85 => [self::QA],
        86 => [self::INITIATOR, self::HOD, self::QA],
        90 => [self::HOD],
        91 => [self::USER_DEPARTMENT],
        92 => [self::QA],
        93 => [self::INITIATOR, self::USER_DEPARTMENT, self::QA],
        98 => [self::HOD],
        99 => [self::QA],
        100 => [self::INITIATOR, self::HOD, self::QA],
    ];

    /* cancel activities notify every active user */
    private const CANCEL_ACTIVITY_IDS = [
        6, 7, 8, 14, 15, 16, 22, 23, 24, 29, 30, 35, 36, 41, 42,
        48, 49, 50, 56, 57, 58, 63, 64, 69, 70, 75, 76, 81, 82,
        87, 88, 94, 95, 96, 101, 102,
    ];

    /* call after a record is created: notifies every initiator, closed when the record leaves its first stage */
    public static function notifyCreated(Model $record): void
    {
        try {
            $record->loadMissing(['process', 'stage']);
            if (!$record->stage) {
                return;
            }
            $performedBy = Auth::user();
            foreach (self::findUsers([self::INITIATOR]) as $user) {
                self::deliver(self::KIND_CREATED, $user, self::INITIATOR, $record, null, $performedBy, null);
            }
        } catch (\Throwable $e) {
            self::logError('ACTIVITY NOTIFICATION ERROR', $e);
        }
    }

    /* call after a stage move is committed: closes the work of the stage that was left, then notifies the next roles */
    public static function notify(Model $record, Activity $activity, ?string $comment = null): void
    {
        try {
            $activity->loadMissing(['fromStage', 'toStage']);
            $performedBy = Auth::user();
            self::completePending($record, $activity->fromStage?->id, $performedBy);
            if (!$activity->toStage) {
                return;
            }
            $isCancel = in_array($activity->id, self::CANCEL_ACTIVITY_IDS, true);
            $roles = $isCancel ? [] : (self::ACTIVITY_ROLES[$activity->id] ?? []);
            if (!$isCancel && empty($roles)) {
                return;
            }
            $kind = match (true) {
                $isCancel => self::KIND_CANCEL,
                in_array(self::INITIATOR, $roles, true) => self::KIND_UPDATE,
                default => self::KIND_ACTION,
            };
            foreach (self::findUsers($roles) as $user) {
                $role = $roles ? ($user->roles->pluck('name')->intersect($roles)->first() ?? $roles[0]) : 'All users';
                self::deliver($kind, $user, $role, $record, $activity, $performedBy, $comment);
            }
        } catch (\Throwable $e) {
            self::logError('ACTIVITY NOTIFICATION ERROR', $e);
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

    /* active users with an email, limited to the given roles (all users when no role is given) */
    private static function findUsers(array $roles): Collection
    {
        $query = User::where('is_active', true)->whereNotNull('email')->with('roles');
        if (!empty($roles)) {
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', $roles));
        }
        return $query->get();
    }

    /* action notifications stay pending until the record leaves the stage, information ones are stored as completed */
    private static function deliver(string $kind, User $user, string $role, Model $record, ?Activity $activity, $performedBy, ?string $comment): void
    {
        $details = self::buildDetails($kind, $user, $role, $record, $activity, $performedBy, $comment);
        $isPending = in_array($kind, [self::KIND_CREATED, self::KIND_ACTION], true);
        $notification = RecordNotification::firstOrCreate(
            [
                'user_id' => $user->id,
                'record_type' => $record->getMorphClass(),
                'record_id' => $record->getKey(),
                'stage_id' => $activity ? $activity->toStage->id : $record->stage_id,
                'activity_id' => $activity?->id,
                'status' => $isPending ? RecordNotification::STATUS_PENDING : RecordNotification::STATUS_COMPLETED,
            ],
            [
                'process_id' => $record->getAttributes()['process_id'] ?? null,
                'role_name' => $role,
                'title' => $details['title'],
                'message' => $details['summary'],
                'performed_by' => $performedBy?->id,
                'completed_at' => $isPending ? null : now(),
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

    private static function buildDetails(string $kind, User $user, string $role, Model $record, ?Activity $activity, $performedBy, ?string $comment): array
    {
        $processName = $record->process?->name ?? 'Process Record';
        $recordNumber = self::resolveRecordNumber($record);
        $fromStage = $activity?->fromStage?->name;
        $toStage = $activity ? $activity->toStage->name : ($record->stage?->name ?? '-');
        $actor = $performedBy?->name ?? 'System';
        $subject = "{$recordNumber} ({$processName})";
        $roleNote = "You received this email because you are assigned the role {$role}.";
        [$badge, $title, $heading, $summary, $footerNote] = match ($kind) {
            self::KIND_CREATED => [
                'Action required',
                "New record created: {$recordNumber}",
                "{$recordNumber} was created",
                "{$subject} was created by {$actor} and is waiting at {$toStage}. Please complete it and move it to the next stage.",
                $roleNote,
            ],
            self::KIND_ACTION => [
                'Action required',
                "Action required: {$recordNumber} is now at {$toStage}",
                "{$recordNumber} needs your attention",
                "{$subject} was moved from {$fromStage} to {$toStage} by {$actor}. Your action is required as {$role}.",
                $roleNote,
            ],
            self::KIND_UPDATE => [
                'Update',
                "Update: {$recordNumber} is now at {$toStage}",
                "{$recordNumber} was updated",
                "{$subject} was moved from {$fromStage} to {$toStage} by {$actor}. No further action is required from you.",
                $roleNote,
            ],
            default => [
                'Cancelled',
                "Cancelled: {$recordNumber}",
                "{$recordNumber} was cancelled",
                "{$subject} was cancelled by {$actor}.",
                'You received this email because you are a registered user of the system.',
            ],
        };
        return [
            'title' => $title,
            'summary' => $summary,
            'badge' => $badge,
            'heading' => $heading,
            'footerNote' => $footerNote,
            'companyName' => self::COMPANY_NAME,
            'recipientName' => $user->name,
            'roleName' => $role,
            'processName' => $processName,
            'recordNumber' => $recordNumber,
            'activityName' => $activity?->name ?? 'Record created',
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

    private static function logError(string $label, \Throwable $e): void
    {
        \Log::error($label, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}