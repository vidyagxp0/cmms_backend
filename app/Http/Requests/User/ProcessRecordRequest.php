<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ProcessRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'process_id' => [
                'required',
                'integer',
                'exists:processes,id',
            ],

            'stage_id' => [
                'required',
                'integer',
                'exists:stages,id',
            ],

            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
            ],

            'initiator_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'short_description' => [
                'required',
                'string',
                'max:255',
            ],

            'initiation_date' => [
                'required',
                'date',
            ],

            'process_data' => [
                'required',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'process_id.required' => 'Process is required.',
            'process_id.exists' => 'Selected process does not exist.',

            'stage_id.required' => 'Stage is required.',
            'stage_id.exists' => 'Selected stage does not exist.',

            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',

            'initiator_id.required' => 'Initiator is required.',
            'initiator_id.exists' => 'Selected initiator does not exist.',

            'short_description.required' => 'Short description is required.',

            'initiation_date.required' => 'Initiation date is required.',
            'initiation_date.date' => 'Initiation date must be a valid date.',
        ];
    }
}