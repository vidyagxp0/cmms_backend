<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\AuditRequest;
use App\Models\Audit;
use App\Helpers\AuditHelper;

class AuditService
{
    /* audit listing */
    public static function getAudits(AuditRequest $request)
    {
        try {

            $audits = Audit::query()
                ->with('user')
                ->where('user_id', auth()->id())
                ->when($request->module, function ($query) use ($request) {
                    $query->where('module', $request->module);
                })
                ->when($request->action, function ($query) use ($request) {
                    $query->where('action', $request->action);
                })
                ->when($request->model, function ($query) use ($request) {
                    $query->where('model', $request->model);
                })
                ->when($request->record_id, function ($query) use ($request) {
                    $query->where('record_id', $request->record_id);
                })
                ->when($request->search, function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->Where('module', 'like', '%' . $request->search . '%')
                            ->orWhere('action', 'like', '%' . $request->search . '%')
                            ->orWhere('old_value', 'like', '%' . $request->search . '%')
                            ->orWhere('new_value', 'like', '%' . $request->search . '%');
                    });
                })
                ->when($request->from_date, function ($query) use ($request) {
                    $query->whereDate('created_at', '>=', $request->from_date);
                })
                ->when($request->to_date, function ($query) use ($request) {
                    $query->whereDate('created_at', '<=', $request->to_date);
                })
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 10);

            $audits->getCollection()->transform(function ($audit) {

                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user?->name,
                    'module' => $audit->module,
                    'model' => $audit->model,
                    'action' => $audit->action,
                    'description' => $audit->description,
                    'record_id' => $audit->record_id,

                    'old_value' => AuditHelper::formatValue(
                        $audit->old_value,
                        $audit->module
                    ),

                    'new_value' => AuditHelper::formatValue(
                        $audit->new_value,
                        $audit->module
                    ),

                    'created_at' => $audit->created_at?->format('d-m-Y H:i:s'),
                ];
            });

            return ResponseHelper::success(
                $audits,
                'Audits fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in AuditService@getAudits', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to retrieve audits.',
                500
            );
        }
    }

    /* human related fields name */
    private static function getFieldLabels($module)
    {
        return match ($module) {

            'Department' => [
                'name' => 'Name',
                'is_active' => 'Active Status',
            ],

            default => [],
        };
    }

    /* formatter */
    private static function formatAuditValue($value, $module)
    {
        if (!$value) {
            return null;
        }

        $labels = self::getFieldLabels($module);

        $formattedValue = [];

        foreach ($value as $key => $data) {
            $label = $labels[$key] ?? $key;
            $formattedValue[$label] = $data;
        }
        return $formattedValue;
    }
}