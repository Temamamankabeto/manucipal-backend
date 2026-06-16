<?php

namespace App\Http\Requests\PaymentCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment.update') || $this->user()?->can('payment.approve');
    }

    public function rules(): array
    {
        $id = $this->route('payment_category')?->id ?? $this->route('payment_category');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('payment_categories', 'name')->ignore($id)],
        ];
    }
}
