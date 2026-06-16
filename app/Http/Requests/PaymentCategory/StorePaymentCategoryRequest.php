<?php

namespace App\Http\Requests\PaymentCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment.create') || $this->user()?->can('payment.update') || $this->user()?->can('payment.approve');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('payment_categories', 'name')],
        ];
    }
}
