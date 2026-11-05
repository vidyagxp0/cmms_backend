<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\RecordNotification;
use Illuminate\Support\Facades\Auth;


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

    public function getMasterData(Request $request)
    {
        try {
            /* read route parameters by name, so URL order doesn't matter */
            $type = $request->route('type');
            $departmentId = $request->route('departmentId');

            /* types that are NOT department wise (fetch all records) */
            $globalTypes = ['instrument_masters', 'spare_instrument_master'];

            /* types that are department wise */
            $departmentWiseTypes = ['field_instrument_master', 'equipment_master'];

            $validator = Validator::make([
                'department_id' => $departmentId,
                'type' => $type,
            ], [
                'type' => [
                    'required',
                    'string',
                    Rule::in(array_merge($globalTypes, $departmentWiseTypes)),
                ],
                /* department is mandatory only for department wise masters */
                'department_id' => [
                    Rule::requiredIf(in_array($type, $departmentWiseTypes, true)),
                    'nullable',
                    'integer',
                    Rule::exists('departments', 'id'),
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
                'instrument_masters' => 'instrument_masters',
                'field_instrument_master' => 'field_instrument_masters',
                'spare_instrument_master' => 'spare_instrument_masters',
                'equipment_master' => 'equipment_masters',
            ];

            $data = DB::table($tables[$type])
                /* apply department filter only for department wise masters */
                ->when(in_array($type, $departmentWiseTypes, true), function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /* get notifications matching the logged-in user's roles */
    public function getNotificationList(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            /* get all roles assigned to the logged-in user */
            $roleNames = $user->roles()
                ->pluck('name')
                ->toArray();

            if (empty($roleNames)) {
                return response()->json([
                    'status' => true,
                    'message' => 'No roles assigned to this user.',
                    'data' => [],
                ]);
            }

            /* match notifications with the user's roles */
            $notifications = RecordNotification::with(['user'])
                ->where('user_id', $user->id)
                ->whereIn('role_name', $roleNames)
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->latest()
                ->paginate($request->integer('per_page', 15));

            return response()->json([
                'status' => true,
                'message' => 'Notifications fetched successfully.',
                'roles' => $roleNames,
                'data' => $notifications,
            ]);

        } catch (\Throwable $e) {
            \Log::error('RECORD NOTIFICATION FETCH ERROR', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch notifications.',
            ], 500);
        }
    }
}