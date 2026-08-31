<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\UserAuditService;
use Illuminate\Http\Request;

class UserAuditController extends Controller
{
    /* process record audit listing */
    public function index(Request $request, $recordId)
    {
        try {
            return UserAuditService::getProcessRecordAudits(
                $recordId,
                $request
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process record audits.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* equipment audit records data */
    public function getEquipmentMasterAudit($recordId)
    {
        try {
            return UserAuditService::getEquipmentMasterAudit(
                $recordId
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve equipment master audits.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}