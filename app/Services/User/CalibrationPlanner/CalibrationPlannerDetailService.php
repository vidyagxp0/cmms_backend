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
    private const CLOSED_DONE_STAGE = 'Closed - Done';

    /* fetch a single process record with related data */
    public static function getCalibrationPlannerRecord($id)
    {
        try {
            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
                'gridRecords',
                'checklistRecords',
            ])->findOrFail($id);
            self::addChildDatesToGrid($processRecord);
            return ResponseHelper::success($processRecord, 'Process record fetched successfully.');
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

    /* recalculate and store the dates of a record and of the planners it was generated from */
    public static function syncPlannerDates($id): void
    {
        $seen = [];
        while ($id && !in_array($id, $seen, true)) {
            $seen[] = $id;
            $record = ProcessRecord::with('gridRecords')->find($id);
            if (!$record) {
                return;
            }
            self::addChildDatesToGrid($record);
            $id = $record->parent_id;
        }
    }

    /* add the calculated dates to every grid row and store them when they changed */
    private static function addChildDatesToGrid(ProcessRecord $planner): void
    {
        $calibrationChildren = ProcessRecord::with('stage')
            ->where('parent_id', $planner->id)
            ->where('process_id', '!=', $planner->process_id)
            ->get();
        $followUpPlanners = self::findFollowUpPlanners($planner);
        $followUpChildren = $followUpPlanners->isEmpty()
            ? collect()
            : ProcessRecord::with('stage')
                ->whereIn('parent_id', $followUpPlanners->pluck('id')->all())
                ->where('process_id', '!=', $planner->process_id)
                ->get();
        $verifiedDates = self::findQaVerifiedDates($calibrationChildren->concat($followUpChildren));
        foreach ($planner->gridRecords as $gridRecord) {
            $original = self::decodeGridData($gridRecord->grid_data);
            $rows = $original;
            foreach ($rows as $index => $row) {
                if (is_array($row)) {
                    $rows[$index] = self::buildGridRow($row, $index, $calibrationChildren, $followUpPlanners, $followUpChildren, $verifiedDates);
                }
            }
            if (empty($rows)) {
                continue;
            }
            if ($rows !== $original) {
                self::storeGridData($gridRecord->id, $rows);
            }
            $gridRecord->setAttribute('grid_data', $rows);
        }
        $planner->setAttribute('child_records', self::summarizeChildren($calibrationChildren, $verifiedDates));
    }

    private static function storeGridData($gridRecordId, array $rows): void
    {
        DB::table('grid_records')->where('id', $gridRecordId)->update([
            'grid_data' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    private static function buildGridRow(array $row, $index, Collection $calibrationChildren, Collection $followUpPlanners, Collection $followUpChildren, array $verifiedDates): array
    {
        $rowId = $row['row_id'] ?? ($index + 1);
        $planned = self::parsePlannerDate(self::readGridValue($row, 'plannedDate'));
        $months = self::resolveCategoryMonths(self::readGridValue($row, 'category'));
        $next = ($planned && $months) ? $planned->copy()->addMonthsNoOverflow($months) : null;
        $rowChildren = self::sortChildren($calibrationChildren->where('row_id', $rowId));
        /* next cycle in the same year: second child of this row, otherwise the child in the follow-up planner */
        $nextChild = ($planned && $next && $next->year === $planned->year)
            ? $rowChildren->get(1)
            : self::findNextCycleChild($row, $next, $followUpPlanners, $followUpChildren);
        $doneDate = self::doneDateOf($rowChildren->first(), $verifiedDates);
        if ($doneDate !== null) {
            $row = self::writeGridValue($row, 'calibrationDoneDate', $doneDate);
        }
        $row = self::writeGridValue($row, 'nextPlannedDate', $next?->toDateString());
        return self::writeGridValue($row, 'nextCalibrationDoneDate', self::doneDateOf($nextChild, $verifiedDates));
    }

    private static function findNextCycleChild(array $row, ?Carbon $next, Collection $followUpPlanners, Collection $followUpChildren)
    {
        $equipmentId = self::readGridValue($row, 'equipmentInstrumentId');
        if (!$next || $equipmentId === null || $equipmentId === '') {
            return null;
        }
        foreach ($followUpPlanners as $followUpPlanner) {
            foreach ($followUpPlanner->gridRecords as $gridRecord) {
                foreach (self::decodeGridData($gridRecord->grid_data) as $followIndex => $followRow) {
                    if (!is_array($followRow) || self::readGridValue($followRow, 'equipmentInstrumentId') !== $equipmentId) {
                        continue;
                    }
                    $followPlanned = self::parsePlannerDate(self::readGridValue($followRow, 'plannedDate'));
                    if (!$followPlanned || !$followPlanned->isSameDay($next)) {
                        continue;
                    }
                    $followRowId = $followRow['row_id'] ?? ($followIndex + 1);
                    return self::sortChildren(
                        $followUpChildren->where('parent_id', $followUpPlanner->id)->where('row_id', $followRowId)
                    )->first();
                }
            }
        }
        return null;
    }

    /* planners of the same process linked through parent_id, followed until none is left */
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

    /* record id => latest QA verification date, closed children only */
    private static function findQaVerifiedDates(Collection $children): array
    {
        $closedIds = $children->filter(fn($child) => self::isClosedDone($child))->pluck('id')->all();
        if (empty($closedIds)) {
            return [];
        }
        return DB::table('record_activity_histories as history')
            ->join('activities as activity', 'activity.id', '=', 'history.activity_id')
            ->whereIn('history.process_record_id', $closedIds)
            ->where('activity.name', self::QA_VERIFIED_ACTIVITY)
            ->groupBy('history.process_record_id')
            ->selectRaw('history.process_record_id, MAX(history.created_at) as verified_at')
            ->get()
            ->pluck('verified_at', 'process_record_id')
            ->all();
    }

    private static function isClosedDone($child): bool
    {
        return strcasecmp(trim((string) ($child->stage?->name ?? '')), self::CLOSED_DONE_STAGE) === 0;
    }

    private static function doneDateOf($child, array $verifiedDates): ?string
    {
        if (!$child || !self::isClosedDone($child)) {
            return null;
        }
        $verifiedAt = $verifiedDates[$child->id] ?? null;
        return $verifiedAt ? Carbon::parse($verifiedAt)->toDateString() : null;
    }

    /* oldest initiation date first */
    private static function sortChildren(Collection $children): Collection
    {
        return $children
            ->sort(fn($a, $b) => [self::initiatedAt($a), $a->id] <=> [self::initiatedAt($b), $b->id])
            ->values();
    }

    /* stored value, the model formats initiation_date for display and that format does not sort */
    private static function initiatedAt($child): string
    {
        return (string) ($child->getRawOriginal('initiation_date') ?? $child->created_at);
    }

    private static function summarizeChildren(Collection $children, array $verifiedDates): array
    {
        return self::sortChildren($children)->map(fn($child) => [
            'id' => $child->id,
            'row_id' => $child->row_id,
            'record_number' => self::readRecordNumber($child),
            'stage' => $child->stage?->name,
            'initiation_date' => $child->initiation_date,
            'qa_verified_on' => self::doneDateOf($child, $verifiedDates),
        ])->all();
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

    /* months to add: Critical 6, Non Critical 12, Indicative 24 */
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

    private static function decodeGridData($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    }

    /* value of a grid field stored as ['key', 'label', 'value'] */
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