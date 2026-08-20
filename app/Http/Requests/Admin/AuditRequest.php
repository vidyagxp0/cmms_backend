<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],

            'module' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'action' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'model' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'record_id' => [
                'sometimes',
                'nullable',
                'integer',
            ],

            'search' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'from_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'to_date' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:from_date',
            ],
        ];
    }
}