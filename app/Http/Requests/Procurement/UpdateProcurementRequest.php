<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requester_type' => ['sometimes', 'required', 'string', 'max:100'],
            'category_id' => ['sometimes', 'required', 'exists:procurement_categories,id'],
            'procurement_type_id' => [
                'sometimes',
                'required',
                Rule::exists('procurement_types', 'id')->where(
                    fn ($query) => $query->where('category_id', $this->input('category_id'))
                ),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'submission_method' => ['nullable', 'string', 'max:80'],
            'budget_code' => ['nullable', 'string', 'max:100'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required_with:items', 'string', 'max:50'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
