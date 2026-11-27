<?php

namespace App\Services\User\CalibrationPlanner;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalibrationPlannerDetailService
{
    private const QA_VERIFIED_ACTIVITY = 'QA Verification Complete';

    /* fetch planner with refreshed dates */
    public static function getCalibrationPlannerRecord($id)
    {
        try {
            $planner = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
                'gridRecords',
                'checklistRecords',
            ])->findOrFail($id);

            self::refreshPlannerDates($planner);

            return ResponseHelper::success($planner, 'Process record fetched successfully.');
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::error('Process record not found.', 404);
        } catch (\Throwable $e) {
            \Log::error('CALIBRATION PLANNER SHOW ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* refresh dates up the parent chain */
    public static function syncPlannerDates($id): void
    {
        $seen = [];
        while ($id && !in_array($id, $seen, true)) {
            $seen[] = $id;
            $record = ProcessRecord::with('gridRecords')->find($id);
            if (!$record) {
                return;
            }
            self::refreshPlannerDates($record);
            $id = $record->parent_id;
        }
    }

    private static function refreshPlannerDates(ProcessRecord $planner): void
    {
        $children = self::findCalibrationChildren([$planner->id], $planner->process_id);
        $followUps = self::findFollowUpPlanners($planner);
        $followChildren = $followUps->isEmpty()
            ? collect()
            : self::findCalibrationChildren($followUps->pluck('id')->all(), $planner->process_id);
        $qaDates = self::findQaDates($children->concat($followChildren)->pluck('id')->all());

        foreach ($planner->gridRecords as $gridRecord) {
            $original = self::decodeGridData($gridRecord->grid_data);
            if (empty($original)) {
                continue;
            }

            $rows = $original;
            foreach ($rows as $index => $row) {
                if (is_array($row)) {
                    $rows[$index] = self::buildGridRow($row, $index, $children, $followUps, $followChildren, $qaDates);
                }
            }

            if ($rows !== $original) {
                self::storeGridData($gridRecord->id, $rows);
            }
            $gridRecord->setAttribute('grid_data', $rows);
        }

        $planner->setAttribute('child_records', self::summarizeChildren($children, $qaDates));
        $planner->setAttribute('qa_verification_rows', self::buildQaVerificationRows($children, $qaDates));
    }

    private static function buildGridRow(array $row, $index, Collection $children, Collection $followUps, Collection $followChildren, array $qaDates): array
    {
        $rowId = (string) self::resolveRowId($row, $index);
        $planned = self::parsePlannerDate(self::readGridValue($row, 'plannedDate'));
        $months = self::resolveCategoryMonths(self::readGridValue($row, 'category'));
        $next = ($planned && $months) ? $planned->copy()->addMonthsNoOverflow($months) : null;

        $rowChildren = self::sortChildren($children->filter(fn($child) => (string) $child->row_id === $rowId));

        /* 2nd child of row, else follow-up planner */
        $nextChild = $rowChildren->get(1) ?? self::findNextCycleChild($row, $next, $followUps, $followChildren);

        $row = self::writeGridValue($row, 'calibrationDoneDate', self::qaDateOf($rowChildren->first(), $qaDates));
        $row = self::writeGridValue($row, 'nextPlannedDate', $next?->toDateString());
        $row = self::writeGridValue($row, 'nextCalibrationDoneDate', self::qaDateOf($nextChild, $qaDates));

        /* child creation flags for the frontend buttons */
        $firstCreated = $rowChildren->isNotEmpty();
        $secondCreated = $firstCreated && $nextChild !== null;

        $row = self::writeGridValue($row, 'is_first_child_created', $firstCreated);
        $row = self::writeGridValue($row, 'is_second_child_created', $secondCreated);

        return self::writeGridValue($row, 'child_count', (int) $firstCreated + (int) $secondCreated);
    }

    private static function findNextCycleChild(array $row, ?Carbon $next, Collection $followUps, Collection $followChildren)
    {
        $equipmentId = self::readGridValue($row, 'equipmentInstrumentId');
        if (!$next || $equipmentId === null || $equipmentId === '') {
            return null;
        }

        foreach ($followUps as $followUp) {
            foreach ($followUp->gridRecords as $gridRecord) {
                foreach (self::decodeGridData($gridRecord->grid_data) as $followIndex => $followRow) {
                    if (!is_array($followRow) || self::readGridValue($followRow, 'equipmentInstrumentId') !== $equipmentId) {
                        continue;
                    }
                    $followPlanned = self::parsePlannerDate(self::readGridValue($followRow, 'plannedDate'));
                    if (!$followPlanned || !$followPlanned->isSameDay($next)) {
                        continue;
                    }
                    $followRowId = (string) self::resolveRowId($followRow, $followIndex);
                    return self::sortChildren(
                        $followChildren->filter(
                            fn($child) => $child->parent_id == $followUp->id && (string) $child->row_id === $followRowId
                        )
                    )->first();
                }
            }
        }
        return null;
    }

    /* same process planners chained by parent_id */
    private static function findFollowUpPlanners(ProcessRecord $planner): Collection
    {
        $followUps = collect();
        $parentIds = [$planner->id];
        while (!empty($parentIds)) {
            $found = ProcessRecord::with('gridRecords')
                ->whereIn('parent_id', $parentIds)
                ->where('process_id', $planner->process_id)
                ->whereNotIn('id', $followUps->pluck('id')->push($planner->id)->all())
                ->get();
            $followUps = $followUps->concat($found);
            $parentIds = $found->pluck('id')->all();
        }
        return $followUps;
    }

    private static function findCalibrationChildren(array $parentIds, $plannerProcessId): Collection
    {
        return ProcessRecord::with('stage')
            ->whereIn('parent_id', $parentIds)
            ->where('process_id', '!=', $plannerProcessId)
            ->get();
    }

    /* record id => QA complete timestamps, oldest first */
    private static function findQaDates(array $recordIds): array
    {
        if (empty($recordIds)) {
            return [];
        }

        $dates = [];
        $rows = DB::table('record_activity_histories as history')
            ->join('activities as activity', 'activity.id', '=', 'history.activity_id')
            ->whereIn('history.process_record_id', $recordIds)
            ->where('activity.name', self::QA_VERIFIED_ACTIVITY)
            ->orderBy('history.created_at')
            ->get(['history.process_record_id', 'history.created_at']);

        foreach ($rows as $row) {
            $dates[$row->process_record_id][] = (string) $row->created_at;
        }
        return $dates;
    }

    /* latest QA complete date of record */
    private static function qaDateOf($child, array $qaDates): ?string
    {
        $entries = $child ? ($qaDates[$child->id] ?? []) : [];
        return $entries ? Carbon::parse(max($entries))->toDateString() : null;
    }

    /* oldest initiation first */
    private static function sortChildren(Collection $children): Collection
    {
        return $children
            ->sort(fn($a, $b) => [self::initiatedAt($a), $a->id] <=> [self::initiatedAt($b), $b->id])
            ->values();
    }

    /* raw value sorts, display format does not */
    private static function initiatedAt($child): string
    {
        return (string) ($child->getRawOriginal('initiation_date') ?? $child->created_at);
    }

    private static function summarizeChildren(Collection $children, array $qaDates): array
    {
        return self::sortChildren($children)->map(fn($child) => [
            'id' => $child->id,
            'row_id' => $child->row_id,
            'record_number' => self::readRecordNumber($child),
            'stage' => $child->stage?->name,
            'initiation_date' => $child->initiation_date,
            'qa_verified_on' => self::qaDateOf($child, $qaDates),
        ])->all();
    }

    private static function buildQaVerificationRows(Collection $children, array $qaDates): array
    {
        return $children
            ->groupBy(fn($child) => (string) $child->row_id)
            ->sortBy(fn($group, $rowId) => (int) $rowId)
            ->map(function (Collection $group, $rowId) use ($qaDates) {
                $items = self::sortChildren($group)->map(function ($child) use ($qaDates) {
                    $entries = $qaDates[$child->id] ?? [];
                    $latest = $entries ? max($entries) : null;
                    return [
                        'id' => $child->id,
                        'record_number' => self::readRecordNumber($child),
                        'stage' => $child->stage?->name,
                        'initiation_date' => $child->initiation_date,
                        'qa_verified_on' => $latest ? Carbon::parse($latest)->toDateString() : null,
                        'qa_verified_at' => $latest,
                        'qa_verification_history' => $entries,
                    ];
                })->values();

                return [
                    'row_id' => $rowId,
                    'total_records' => $items->count(),
                    'verified_records' => $items->whereNotNull('qa_verified_at')->count(),
                    'records' => $items->all(),
                ];
            })
            ->values()
            ->all();
    }

    private static function readRecordNumber($record): ?string
    {
        foreach (self::decodeGridData($record->process_data) as $field) {
            if (is_array($field) && ($field['key'] ?? null) === 'recordNumber') {
                return $field['value'] ?? null;
            }
        }
        return null;
    }

    /* C = 6, NC = 12, I = 24 months */
    private static function resolveCategoryMonths($category): ?int
    {
        $category = strtolower(preg_replace('/[^a-z]/i', '', (string) $category));
        if ($category === 'nc' || str_starts_with($category, 'non')) {
            return 12;
        }
        if ($category === 'c' || $category === 'critical') {
            return 6;
        }
        if ($category === 'i' || $category === 'indicative') {
            return 24;
        }
        return null;
    }

    private static function parsePlannerDate($value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* row_id may be nested, read innermost */
    private static function resolveRowId(array $row, $index)
    {
        $value = $row['row_id'] ?? null;
        while (is_array($value)) {
            $value = $value['value'] ?? null;
        }
        return ($value === null || $value === '') ? ($index + 1) : $value;
    }

    private static function storeGridData($gridRecordId, array $rows): void
    {
        DB::table('grid_records')->where('id', $gridRecordId)->update([
            'grid_data' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    private static function decodeGridData($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    }

    private static function readGridValue(array $row, string $key)
    {
        $field = $row[$key] ?? null;
        return is_array($field) ? ($field['value'] ?? null) : $field;
    }

    private static function writeGridValue(array $row, string $key, $value): array
    {
        $field = (isset($row[$key]) && is_array($row[$key])) ? $row[$key] : ['key' => $key, 'label' => $key];
        $field['value'] = $value;
        $row[$key] = $field;
        return $row;
    }
}