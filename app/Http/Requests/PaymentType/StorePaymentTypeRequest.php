<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment.create') || $this->user()?->can('payment.update') || $this->user()?->can('payment.approve');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:payment_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_types', 'name')->where(fn ($query) => $query->where('category_id', $this->input('category_id'))),
            ],
        ];
    }
}
