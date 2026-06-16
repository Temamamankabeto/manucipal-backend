<?php

namespace App\Http\Requests\ProcurementCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcurementCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.update') || $this->user()?->can('procurement.approve');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('procurement_categories', 'name')->ignore($this->route('procurement_category'))],
        ];
    }
}
