<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\RecordActivityService;
use Illuminate\Http\Request;

class RecordActivityController extends Controller
{
    /* activity history */
    public function index($recordId)
    {
        try {
            return RecordActivityService::getActivityHistory(
                $recordId
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity history.',
            ], 500);
        }
    }
}
