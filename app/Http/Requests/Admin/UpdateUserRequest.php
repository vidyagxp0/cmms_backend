<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'salutation' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'person_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'person_id')->ignore($userId),
            ],

            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($userId),
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'mobile_no' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
            ],

            'department_id' => [
                'sometimes',
                'nullable',
                'exists:departments,id',
            ],

            'roles' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'roles.*' => [
                'exists:roles,id',
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
            'person_id.string' => 'Person ID must be a valid string.',
            'person_id.unique' => 'This Person ID is already assigned to another user.',

            'name.string' => 'Name must be a valid string.',

            'username.string' => 'Username must be a valid string.',
            'username.unique' => 'This username is already taken.',

            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',

            'roles.array' => 'Roles must be provided as an array.',
            'roles.*.exists' => 'One or more selected roles are invalid.',

            'department_id.exists' => 'The selected department does not exist.',

            'password.min' => 'Password must be at least 8 characters.',

            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}