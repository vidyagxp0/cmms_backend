<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditRequest;
use App\Services\Admin\AuditService;

class AuditController extends Controller
{
    public function index(AuditRequest $request)
    {
        try {
            return AuditService::getAudits($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audits.',
            ], 500);
        }
    }
}