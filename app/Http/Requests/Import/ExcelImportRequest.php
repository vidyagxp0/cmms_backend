<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'euipmentMasterType' => [
                'required',
                'string',
                Rule::in([
                    'master_instrument',
                    'field_instrument_master',
                    'spare_instrument_master',
                    'equipment_master',
                ]),
            ],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:' . config('cmms-import.max_file_kb', 20480),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'euipmentMasterType.required' => 'Please select an equipment master category.',
            'euipmentMasterType.in' => 'The selected equipment master category is invalid.',
            'department_id.required' => 'Please select a department.',
            'department_id.integer' => 'The department ID must be a valid number.',
            'department_id.exists' => 'The selected department does not exist.',
            'file.required' => 'Please upload an Excel file.',
            'file.mimes' => 'Only XLS and XLSX files are supported.',
            'file.max' => 'The uploaded file exceeds the maximum allowed size.',
        ];
    }
}
