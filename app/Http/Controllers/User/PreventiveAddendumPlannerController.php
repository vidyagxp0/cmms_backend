<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\AddendumFieldInstrument\PreventiveAddendumPlannerService;
use Illuminate\Http\Request;
use App\Http\Requests\User\RecordActivityRequest;

class PreventiveAddendumPlannerController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return PreventiveAddendumPlannerService::storePrevetineAddendum($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create process record.',
            ], 500);
        }
    }

    /* process record details */
    public function show($id)
    {
        try {
            return PreventiveAddendumPlannerService::getPrevetineAddendumPlanner($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process record.',
            ], 500);
        }
    }

    /* update process record */
    public function update(Request $request,$id) 
    {
        try {
            return PreventiveAddendumPlannerService::updatePrevetineAddendumPlanner(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update process record.',
            ], 500);
        }
    }

    /* move process record stage */
    public function moveStage(RecordActivityRequest $request, $id) 
    {
        try {
            return PreventiveAddendumPlannerService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update process record stage.',
            ], 500);
        }
    }

    /* get Addendum Field Instrument audit data */
    // public function prventiveAddendumPlannerAudit(Request $request, $id)
    // {
    //     try {
    //         return AddendumFieldInstrumentAuditService::getProcessRecordAudits(
    //             $id,
    //             $request
    //         );
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve process record audits.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /* Addendum Field Instrument single report */
    // public function prevetineAddendumSingleReport($id) 
    // {
    //     try {
    //         return AddendumFieldInstrumentReportService::generateReport(
    //             $id
    //         );
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load report.',
    //         ], 500);
    //     }
    // }
}
