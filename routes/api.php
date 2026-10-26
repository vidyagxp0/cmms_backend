<?php

use Illuminate\Support\Facades\Route;

/* common controller */
use App\Http\Controllers\Auth\AuthController;

/* admin controller */
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\AuditController;

/* users controller */
use App\Http\Controllers\User\ProcessConfigController;
use App\Http\Controllers\User\ProcessRecordController;
use App\Http\Controllers\User\EquipmentMasterController;
use App\Http\Controllers\User\CalibrationPlannerController;
use App\Http\Controllers\User\CalibrationManagementController;
use App\Http\Controllers\User\UserAuditController;
use App\Http\Controllers\User\PreventiveRecordController;
use App\Http\Controllers\User\RecordActivityController;
use App\Http\Controllers\User\PreventiveMaintenanceController;
use App\Http\Controllers\User\FieldInstrumentMasterController;
use App\Http\Controllers\User\FieldInstrumentPlannerController;
use App\Http\Controllers\User\AddendumFieldInstrumentController;
use App\Http\Controllers\User\SpareInstrumentMasterController;

/* common routes for user/admin */
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {

    /* common profile urls */
    Route::get('/profile', [AuthController::class, 'profile'])->name('user-profile');
    Route::put('/update/profile', [AuthController::class,'updateProfile'])->name('update-profile');
    Route::put('/change-password', [AuthController::class,'changePassword'])->name('change-password');
    Route::post('/logout', [ AuthController::class,'logout'])->name('logout');

    /* role based users */
    Route::get('/role-based-users', [AuthController::class, 'getRoleBasedUsers'])->name('role-based-users');

    Route::post('/logout', [AuthController::class, 'logout']);

    /* admin apis */
    Route::middleware('role:Admin')->prefix('admin')->group(function () {

        /* user activity logs */
        Route::get('/user-activity-logs', [ AuthController::class,'getUserActivities'])->name('user-activity-logs');

        /* departments routes */
        Route::get('/departments-listing', [DepartmentController::class, 'index'])->name('departments-listing');
        Route::post('/store-department', [DepartmentController::class, 'store'])->name('store-department');
        Route::get('/department-detail/{id}', [DepartmentController::class, 'show'])->name('department-details');
        Route::put('/update-department/{id}', [DepartmentController::class, 'update'])->name('update-department');
        Route::delete('/delete-department/{id}', [DepartmentController::class, 'destroy'])->name('delete-department');
        Route::patch('/toggle-department/{id}', [DepartmentController::class, 'toggleActive'])->name('toggle-department');

        /* roles routes */
        Route::get('/roles-listing', [RoleController::class, 'index'])->name('roles-listing');
        Route::post('/store-role', [RoleController::class, 'store'])->name('store-role');
        Route::get('/role-detail/{id}', [RoleController::class, 'show'])->name('role-details');
        Route::put('/update-role/{id}', [RoleController::class, 'update'])->name('update-role');
        Route::delete('/delete-role/{id}', [RoleController::class, 'destroy'])->name('delete-role');
        Route::patch('/toggle-role/{id}', [RoleController::class, 'toggleActive'])->name('toggle-role');

        /* user routes */
        Route::get('/users-pid', [UserController::class, 'getUserPID'])->name('users-pid');
        Route::get('/users-listing', [UserController::class, 'index'])->name('users-listing');
        Route::post('/store-user', [UserController::class, 'store'])->name('store-user');
        Route::get('/user-detail/{id}', [UserController::class, 'show'])->name('user-details');
        Route::put('/update-user/{id}', [UserController::class, 'update'])->name('update-user');
        Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('delete-user');
        Route::patch('/toggle-user/{id}', [UserController::class, 'toggleActive'])->name('toggle-user');

        /* admin audit route */
        Route::get('/audit-listing', [AuditController::class, 'index'])->name('audit-listing');
    });

    /* user apis */ 
    Route::middleware('users')->prefix('user')->group(function () {

        /* process/stages/activities routes */
        Route::get('/process-list', [ProcessConfigController::class, 'getProcesses'])->name('process-listing');
        Route::get('/stages-list/{processId}', [ProcessConfigController::class, 'getProcessStages'])->name('stages-list');
        Route::get('/activities-list/{stageId}', [ProcessConfigController::class, 'getStageActivities'])->name('activities-list');        

        /* process records listing routes */
        Route::get('/get-calibration-planner-records', [ProcessRecordController::class, 'getCalibrationPlannerRecords'])->name('get-calibration-planner-records');
        Route::get('/get-calibration-management-records', [ProcessRecordController::class, 'getCalibrationManagementRecords'])->name('get-calibration-management-records');
        Route::get('/get-preventive-planner-records', [ProcessRecordController::class, 'getPreventiveMaintenancePlannerRecords'])->name('get-preventive-planner-records');
        Route::get('/get-preventive-maintenance-records', [ProcessRecordController::class, 'getPreventiveMaintenanceRecords'])->name('get-preventive-maintenance-records');

       
        /* calibration planner routes */
        Route::post('/store-calibration-planner-record', [CalibrationPlannerController::class, 'store'])->name('store-calibration-planner-record');
        Route::get('/show-calibration-planner-record/{id}', [CalibrationPlannerController::class, 'show'])->name('show-calibration-planner-record');
        Route::put('/update-calibration-planner-record/{id}', [CalibrationPlannerController::class, 'update'])->name('update-calibration-planner-record');
        Route::get('/calibration-planner-singleReport/{id}', [CalibrationPlannerController::class, 'calibrationSingleReport'])->name('calibration-planner-singleReport');
        Route::get('/calibration-audit-listing/{recordId}', [UserAuditController::class, 'index'])->name('calibration-audit-listing');
        Route::get('/calibration-audit-report/{id}', [UserAuditController::class, 'calibrationPlannerAuditReport'])->name('calibration-audit-report');

        /* calibration management routes */
        Route::post('/store-calibration-management-record', [CalibrationManagementController::class, 'store'])->name('store-calibration-management-record');
        Route::get('/show-calibration-management-record/{id}', [CalibrationManagementController::class, 'show'])->name('show-calibration-management-record');
        Route::put('/update-calibration-management-record/{id}', [CalibrationManagementController::class, 'update'])->name('update-calibration-management-record');
        Route::post('/calibrationManagement-record-stage/{id}',[CalibrationManagementController::class, 'moveStage'])->name('calibrationManagement-record-stage');
        Route::get('/calibration-management-singleReport/{id}', [CalibrationManagementController::class, 'calibrationManagementSingleReport'])->name('calibration-management-singleReport');
        Route::get('/calibration-management-audit/{id}', [CalibrationManagementController::class, 'calibrationManagementAudit'])->name('calibration-management-audit');

        /* preventive planner routes */
        Route::post('/store-preventive-planner-record', [PreventiveRecordController::class, 'store'])->name('store-preventive-planner-record');
        Route::get('/show-preventive-planner-record/{id}', [PreventiveRecordController::class, 'show'])->name('show-preventive-planner-record');
        Route::put('/update-preventive-planner-record/{id}', [PreventiveRecordController::class, 'update'])->name('update-preventive-planner-record');
        Route::post('/preventive-planner-record-stage/{id}',[PreventiveRecordController::class, 'moveStage'])->name('preventive-planner-record-stage');
        Route::get('/preventive-planner-singleReport/{id}', [PreventiveRecordController::class, 'preventivePlannerSingleReport'])->name('preventive-planner-singleReport');
        Route::get('/preventive-planner-audit-listing/{recordId}', [UserAuditController::class, 'preventivePlannerAudit'])->name('preventive-planner-audit-listing');


        /* preventive maintenance routes */
        Route::post('/store-preventive-maintenance-record', [PreventiveMaintenanceController::class, 'store'])->name('store-preventive-maintenance-record');
        Route::get('/show-preventive-maintenance-record/{id}', [PreventiveMaintenanceController::class, 'show'])->name('show-preventive-maintenance-record');
        Route::put('/update-preventive-maintenance-record/{id}', [PreventiveMaintenanceController::class, 'update'])->name('update-preventive-maintenance-record');
        Route::post('/preventive-maintenance-record-stage/{id}',[PreventiveMaintenanceController::class, 'moveStage'])->name('preventive-maintenance-record-stage');
        Route::get('/preventive-maintenance-audit-listing/{recordId}', [UserAuditController::class, 'preventiveMaintenanceAudit'])->name('preventive-maintenance-audit-listing');
        Route::get('/preventive-maintenance-singleReport/{id}',[PreventiveMaintenanceController::class, 'preventiveMaintenanceReport'])->name('preventive-maintenance-singleReport');

        /* process records routes */
        Route::get('/equipment-master-records', [ProcessRecordController::class, 'equipmentMaster'])->name('equipment-master-records');
        Route::get('/generate-record-number/{processId}', [ProcessRecordController::class, 'generateRecordNumber'])->name('generate-record-number');
        Route::get('/user-record-permission/{recordId}',[ProcessRecordController::class, 'checkRecordPermission'])->name('user-record-permission');

        /* global attachment apis */
        Route::post('/upload-attachment/{recordId}',[ProcessRecordController::class, 'uploadAttachments'])->name('upload-attachment');

        /* record activity routes */
        Route::post('/calibrationPlanner-record-stage/{id}',[CalibrationPlannerController::class, 'moveStage'])->name('calibrationPlanner-record-stage');
        Route::get('/user-activity-history/{recordId}/{processId}', [RecordActivityController::class, 'index'])->name('user-activity-history');

        /* equipment master routes */
        Route::get('/all-equipment-masters', [EquipmentMasterController::class, 'getAllEquipments'])->name('all-equipment-masters');
        Route::get('/equipment-master-listing',[EquipmentMasterController::class, 'index'])->name('equipment-master-listing');
        Route::post('/store-equipment-master',[EquipmentMasterController::class, 'store'])->name('store-equipment-master');
        Route::get('/equipment-master-detail/{id}',[EquipmentMasterController::class, 'show'])->name('equipment-master-detail');
        Route::put('/update-master-equipment/{id}',[EquipmentMasterController::class, 'update'])->name('update-master-equipment');
        Route::get('/equipment-master-checklist/{equipmentId}/{frequency}', [EquipmentMasterController::class, 'getChecklistByFrequency'])->name('equipment-master-checklist');
        Route::get('/instruments/audit-trail/{id}', [EquipmentMasterController::class, 'getInstrumentAuditTrail'])->name('instrument.audit.details');
        Route::post('/instrument-master-record-stage/{id}',[EquipmentMasterController::class, 'moveStage'])->name('instrument-master-record-stage');
        Route::get('/instrument-record-permission/{recordId}',[EquipmentMasterController::class, 'checkInstrumentRecordPermission'])->name('instrument-record-permission');
        Route::get('/instrument-singleReport/{id}',[EquipmentMasterController::class, 'instrumentSingleReport'])->name('instrument-singleReport');

        /* field instrument routes */
        Route::get('/all-fields-instrument-masters', [FieldInstrumentMasterController::class, 'getAllFieldIntrument'])->name('all-fields-instrument-masters');
        Route::get('/fields-instrument-master-listing',[FieldInstrumentMasterController::class, 'index'])->name('fields-instrument-master-listing');
        Route::post('/store-fields-instrument-master',[FieldInstrumentMasterController::class, 'store'])->name('store-fields-instrument-master');
        Route::get('/fields-instrument-master-detail/{id}',[FieldInstrumentMasterController::class, 'show'])->name('fields-instrument-master-detail');
        Route::put('/update-master-fields-instrument/{id}',[FieldInstrumentMasterController::class, 'update'])->name('update-master-fields-instrument');
        Route::get('/fields-instrument-master-checklist/{equipmentId}/{frequency}', [FieldInstrumentMasterController::class, 'getChecklistByFrequency'])->name('fields-instrument-master-checklist');
        Route::get('/fields-instruments/audit-trail/{id}', [FieldInstrumentMasterController::class, 'getFieldInstrumentAuditTrail'])->name('instrument.audit.details');
        Route::post('/fields-instrument-record-stage/{id}',[FieldInstrumentMasterController::class, 'moveStage'])->name('fields-instrument-record-stage');
        Route::get('/fieldInstrument-singleReport/{id}',[FieldInstrumentMasterController::class, 'fieldInstrumentSingleReport'])->name('fieldInstrument-singleReport');
        Route::get('/field-instrument-record-permission/{recordId}',[FieldInstrumentMasterController::class, 'checkFieldInstrumentRecordPermission'])->name('field-instrument-record-permission');
        Route::get('/filter-equipment-masters',[EquipmentMasterController::class, 'getFilteredEquipments'])->name('filter-equipment-masters');
        Route::get('/equipment-master-audit-listing/{recordId}', [UserAuditController::class, 'getEquipmentMasterAudit'])->name('equipment-master-audit-listing');

        /* field instrument planner routes */
        Route::get('/get-field-instrument-planner-records', [ProcessRecordController::class, 'getFieldInstrumentPlannerRecords'])->name('get-field-instrument-planner-records');
        Route::get('/get-all-field-instrument-planner-records', [ProcessRecordController::class, 'getAllFieldInstrumentPlannerRecords'])->name('get-all-field-instrument-planner-records');
        Route::post('/store-field-instrument-planner-record', [FieldInstrumentPlannerController::class, 'store'])->name('store-field-instrument-planner-record');
        Route::get('/show-field-instrument-planner-record/{id}', [FieldInstrumentPlannerController::class, 'show'])->name('show-field-instrument-planner-record');
        Route::match(['put', 'post'], '/update-field-instrument-planner-record/{id}', [FieldInstrumentPlannerController::class, 'update'])->name('update-field-instrument-planner-record');
        Route::post('/field-instrument-planner-record-stage/{id}', [FieldInstrumentPlannerController::class, 'moveStage'])->name('field-instrument-planner-record-stage');
        Route::get('/field-instrument-planner-singleReport/{id}', [FieldInstrumentPlannerController::class, 'fieldInstrumentPlannerSingleReport'])->name('field-instrument-planner-singleReport');
        Route::get('/field-instrument-planner-audit-listing/{recordId}', [UserAuditController::class, 'fieldInstrumentPlannerAudit'])->name('field-instrument-planner-audit-listing');

        /* Addendum to Field Instrument Calibration routes */
        Route::get('/addendum-field-instrument-planner-records', [ProcessRecordController::class, 'getAddendumFieldInstrumentRecords'])->name('get-addendum-field-instrument-planner-records');
        Route::post('/store-addendum-field-instrument-planner-record', [AddendumFieldInstrumentController::class, 'store'])->name('store-addendum-field-instrument-planner-record');
        Route::get('/show-addendum-field-instrument-planner-record/{id}', [AddendumFieldInstrumentController::class, 'show'])->name('show-addendum-field-instrument-planner-record');
        Route::match(['put', 'post'], '/update-addendum-field-instrument-planner-record/{id}', [AddendumFieldInstrumentController::class, 'update'])->name('update-addendum-field-instrument-planner-record');
        Route::post('/addendum-field-instrument-planner-moveStage/{id}', [AddendumFieldInstrumentController::class, 'moveStage'])->name('field-addendum-instrument-planner-record-stage');
        Route::get('/addendum-field-instrument-planner-audit/{id}', [AddendumFieldInstrumentController::class, 'addendumFieldInstrumentAudit'])->name('field-addendum-instrument-planner-audit');
        Route::get('/addendum-field-instrument-planner-singleReport/{id}', [AddendumFieldInstrumentController::class, 'addendumFieldInstrumentSingleReport'])->name('field-addendum-instrument-planner-singleReport');

        /* Spare Instrument Master Service routes */
        Route::get('/all-spare-instrument-masters', [SpareInstrumentMasterController::class, 'getAllSpareInstrument'])->name('all-spare-instrument-masters');
        Route::get('/spare-instrument-master-listing',[SpareInstrumentMasterController::class, 'index'])->name('spare-instrument-master-listing');
        Route::post('/store-spare-instrument-master',[SpareInstrumentMasterController::class, 'store'])->name('store-spare-instrument-master');
        Route::get('/spare-instrument-master-detail/{id}',[SpareInstrumentMasterController::class, 'show'])->name('spare-instrument-master-detail');
        Route::put('/update-master-spare-instrument/{id}',[SpareInstrumentMasterController::class, 'update'])->name('update-master-spare-instrument');
        Route::get('/spare-instruments/audit-trail/{id}', [SpareInstrumentMasterController::class, 'getSpareInstrumentAuditTrail'])->name('instrument.audit.details');
        Route::post('/spare-instrument-record-stage/{id}',[SpareInstrumentMasterController::class, 'moveStage'])->name('spare-instrument-record-stage');
        Route::post('/spare-instrument-record-permission/{id}',[SpareInstrumentMasterController::class, 'checkSpareInstrumentRecordPermission'])->name('spare-instrument-record-permission');
        Route::get('/spare-instrument-singleReport/{id}',[SpareInstrumentMasterController::class, 'SpareInstrumentSingleReport'])->name('spare-instrument-singleReport');
    });
});