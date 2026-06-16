<?php

namespace App\Http\Requests\Authz;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('tables.create'); }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'max:50', 'unique:dining_tables,table_number'],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:100'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'waiter_ids' => ['nullable', 'array'],
            'waiter_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
