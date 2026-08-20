<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'salutation' => [
                'nullable',
                'string',
                'max:20',
            ],

            'person_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'person_id')->ignore($userId),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($userId),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'mobile_no' => [
                'nullable',
                'string',
                'max:20',
            ],

            'password' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'string',
                'min:8',
            ],

            'department_id' => [
                'nullable',
                'exists:departments,id',
            ],

            'roles' => [
                'required',
                'array',
                'min:1',
            ],

            'roles.*' => [
                'integer',
                'exists:roles,id',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
