<?php

namespace App\Http\Requests\ProcurementType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcurementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.update') || $this->user()?->can('procurement.approve');
    }

    public function rules(): array
    {
        $type = $this->route('procurement_type');

        return [
            'category_id' => ['required', 'integer', 'exists:procurement_categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('procurement_types', 'name')->ignore($type)->where(fn ($query) => $query->where('category_id', $this->input('category_id')))],
        ];
    }
}
