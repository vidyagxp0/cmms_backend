<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->ignore($departmentId),
            ],

            'is_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Department name must be a valid string.',
            'name.unique' => 'This department name already exists.',

            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}