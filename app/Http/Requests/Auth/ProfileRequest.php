<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Name must be a valid string.',
            'name.max' => 'Name may not be greater than 255 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'email.max' => 'Email may not be greater than 255 characters.',
        ];
    }
}