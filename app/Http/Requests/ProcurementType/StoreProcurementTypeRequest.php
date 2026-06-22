<?php

namespace App\Http\Requests\ProcurementType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user?->can('procurement.create') || $user?->can('procurement.update') || $user?->can('procurement.approve');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:procurement_categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('procurement_types', 'name')->where(fn ($query) => $query->where('category_id', $this->input('category_id')))],
        ];
    }
}
