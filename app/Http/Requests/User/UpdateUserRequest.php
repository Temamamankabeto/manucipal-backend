<?php

namespace App\Http\Requests\User;

use App\Models\User;
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
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'address' => ['nullable', 'string', 'max:500'],
    
        'professional_level' => [
            'nullable',
            'string',
            Rule::in(['III', 'IV']),
            
        ],

        'admin_level' => ['nullable', Rule::in([User::LEVEL_CITY, User::LEVEL_SUBCITY, User::LEVEL_WOREDA, User::LEVEL_ZONE])],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'department_id' => [
            'nullable',
            'integer',
            Rule::exists('departments', 'id')->where(fn ($q) => $q->where('office_id', $this->input('office_id'))),
        ],

        'sub_city_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'woreda_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'zone_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
        'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

        'stamp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

        'titer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'role' => [
                'required',
                'string',
                'max:100',
                Rule::in(User::userManagementRoleNames()),
            ],
        ];
    }
}
