<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\Dashboard\DashboardService;

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
}