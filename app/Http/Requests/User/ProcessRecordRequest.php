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
            'search' => [
                'nullable',
                'string',
            ],

            'process_id' => [
                'nullable',
                'integer',
                'exists:processes,id',
            ],

            'from_date' => [
                'nullable',
                'date',
            ],

            'to_date' => [
                'nullable',
                'date',
                'after_or_equal:from_date',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}