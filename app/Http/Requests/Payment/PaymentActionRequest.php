<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->input('action') === 'reject' && ! $this->filled('reason') && $this->filled('note')) {
            $this->merge([
                'reason' => $this->input('note'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                Rule::in([
                    'submit',
                    'manager_approve',
                    'budget_tl_approve',
                    'expert_complete',
                    'budget_tl_final_approve',
                    'manager_final_approve',
                    'records_process',
                    'finance_complete',
                    'reject',
                ]),
            ],
            'note' => ['nullable', 'string'],
            'reason' => ['required_if:action,reject', 'nullable', 'string'],
            'send_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'current_handler_id' => ['nullable', 'integer', 'exists:users,id'],
            'expert_user_id' => ['required_if:action,budget_tl_approve', 'nullable', 'integer', 'exists:users,id'],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'budget_code' => ['nullable', 'string', 'max:100'],
            'office_code' => ['nullable', 'string', 'max:100'],
            'budget_year' => ['nullable', 'string', 'max:20'],
                        'reference_no' => ['nullable', 'string', 'max:100'],
                        'official_date' => ['nullable', 'date'],
            'paid_amount' => ['required_if:action,finance_complete', 'nullable', 'numeric', 'min:0.01'],
            'paid_date' => ['required_if:action,finance_complete', 'nullable', 'date'],
            'voucher_no' => ['nullable', 'string', 'max:100'],
            'finance_remark' => ['nullable', 'string'],

            'items' => ['nullable', 'array'],
            'items.*.budget_code' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],

            'per_diem' => ['nullable', 'array'],
            'per_diem.common' => ['nullable', 'array'],
            'per_diem.common.program' => ['nullable', 'string', 'max:255'],
            'per_diem.common.purpose' => ['nullable', 'string'],
            'per_diem.common.pi_code' => ['nullable', 'string', 'max:100'],
            'per_diem.common.budget_code' => ['nullable', 'string', 'max:100'],
            'per_diem.common.office_name' => ['nullable', 'string', 'max:255'],
            'per_diem.common.departure_location' => ['nullable', 'string', 'max:255'],
            'per_diem.common.destination' => ['nullable', 'string', 'max:255'],
            'per_diem.common.departure_date' => ['nullable', 'date'],
            'per_diem.common.return_date' => ['nullable', 'date'],
            'per_diem.common.transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'per_diem.common.daily_per_diem_rate' => ['nullable', 'numeric', 'min:0'],
            'per_diem.common.approved_budget' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees' => ['nullable', 'array'],
            'per_diem.employees.*.employee_name' => ['required_with:per_diem.employees', 'string', 'max:255'],
            'per_diem.employees.*.salary_level' => ['nullable', 'string', 'max:50'],
            'per_diem.employees.*.salary_amount' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.transportation_type' => ['nullable', 'string', 'max:100'],
            'per_diem.employees.*.departure_location' => ['nullable', 'string', 'max:255'],
            'per_diem.employees.*.destination' => ['nullable', 'string', 'max:255'],
            'per_diem.employees.*.departure_date' => ['nullable', 'date'],
            'per_diem.employees.*.departure_time' => ['nullable', 'date_format:H:i'],
            'per_diem.employees.*.return_date' => ['nullable', 'date'],
            'per_diem.employees.*.return_time' => ['nullable', 'date_format:H:i'],
            'per_diem.employees.*.number_of_days' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.breakfast_deduction' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.lunch_deduction' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.dinner_deduction' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.accommodation_deduction' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.transport_cost' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.fuel_cost' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.other_cost' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.daily_rate' => ['nullable', 'numeric', 'min:0'],
            'per_diem.employees.*.work_description' => ['nullable', 'string'],
            'per_diem.employees.*.is_selected' => ['nullable', 'boolean'],
        ];
    }
}
