<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function counts()
    {
        try {
            $data = $this->dashboardService->getDashboardCounts();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard counts fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard counts.',
            ], 500);
        }
    }

    public function getMasterData($departmentId, $type)
    {
        try {
            $validator = Validator::make([
                'department_id' => $departmentId,
                'type' => $type,
            ], [
                'department_id' => [
                    'required',
                    'integer',
                    Rule::exists('departments', 'id'),
                ],
                'type' => [
                    'required',
                    'string',
                    Rule::in([
                        'master_instrument',
                        'field_instrument_master',
                        'spare_instrument_master',
                        'equipment_master',
                    ]),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $tables = [
                'master_instrument' => 'instrument_masters',
                'field_instrument_master' => 'field_instrument_masters',
                'spare_instrument_master' => 'spare_instrument_masters',
                'equipment_master' => 'equipment_masters',
            ];

            $data = DB::table($tables[$type])
                ->where('department_id', $departmentId)
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Master data fetched successfully.',
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch master data.',
            ], 500);
        }
    }
}