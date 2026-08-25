<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\UserAuditService;

class UserAuditController extends Controller
{
    /* process record audit listing */
    public function index($recordId)
    {
        try {
            return UserAuditService::getProcessRecordAudits(
                $recordId
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process record audits.',
            ], 500);
        }
    }
}