<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'equipment_id' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('equipment_masters', 'equipment_id')
                    ->ignore($this->route('id')),
            ],

            'make' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'model' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'equipment_type' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Equipment name is required.',

            'equipment_id.required' => 'Equipment ID is required.',
            'equipment_id.unique' => 'Equipment ID already exists.',

            'make.required' => 'Equipment make is required.',

            'model.required' => 'Equipment model is required.',

            'equipment_type.required' => 'Equipment type is required.',
        ];
    }
}