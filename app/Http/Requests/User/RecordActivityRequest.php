<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class RecordActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_id' => [
                'required',
                'integer',
                'exists:activities,id',
            ],

            'comment' => [
                'nullable',
                'string',
            ],
        ];
    }
}