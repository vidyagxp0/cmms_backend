<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Models\Audit;

class UserAuditService
{
    /* get process record audit history */
    public static function getProcessRecordAudits($recordId)
    {
        try {

            $audits = Audit::with('user')
                ->where('record_id', $recordId)
                ->where('model', 'App\Models\ProcessRecord')
                ->orderBy('id', 'desc')
                ->get();

            $audits->transform(function ($audit) {

                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user?->name,
                    'module' => $audit->module,
                    'model' => $audit->model,
                    'action' => $audit->action,
                    'description' => $audit->description,
                    'record_id' => $audit->record_id,

                    'old_value' => self::prepareValue(
                        $audit->old_value
                    ),
                    'new_value' => self::prepareValue(
                        $audit->new_value
                    ),
                    'created_at' => $audit->created_at ? $audit->created_at->format('d-m-Y H:i:s') : null,
                ];
            });

            return ResponseHelper::success(
                $audits,
                'Process record audits fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve process record audits.',
                500
            );
        }
    }


    /* prepare audit value */
    private static function prepareValue($value)
    {
        if ($value === null) {
            return null;
        }

        /* if db return json string then decode it */
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return $value;
        }
        return self::cleanArray($value);
    }


    /* clean audit array */
    private static function cleanArray(array $data)
    {
        $result = [];

        foreach ($data as $key => $value) {
            /* clean audit array */
            if (is_array($value)) {

                $result[$key] = self::cleanArray($value);

                continue;
            }

            /* nested json string */
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $result[$key] = self::cleanArray($decoded);
                    } else {
                        $result[$key] = $decoded;
                    }
                    continue;
                }
            }
            $result[$key] = $value;
        }

        return $result;
    }
}