<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'requester_type' => ['required', 'string', 'max:100'],
            'requesting_entity' => ['nullable', 'string', 'max:255'],
            'current_handler_id' => ['nullable', 'integer', 'exists:users,id'],
            'request_type' => ['nullable', 'string', 'max:255'],
            'payment_category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'payment_type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'payment_category' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.invoice_no' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required_with:items', 'string', 'max:50'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.remark' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
