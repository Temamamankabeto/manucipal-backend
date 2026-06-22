<?php

namespace App\Http\Requests\PaymentType;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentTypeRequest extends FormRequest
{
    private function isSuperAdmin(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(User::ROLE_SUPER_ADMIN, 'sanctum')
            || $user->hasRole(User::ROLE_SUPER_ADMIN)
            || $user->getRoleNames()->contains(fn ($role) => strtolower((string) $role) === strtolower(User::ROLE_SUPER_ADMIN));
    }

    public function authorize(): bool
    {
        return $this->isSuperAdmin() || $this->user()?->can('payment.update') || $this->user()?->can('payment.approve');
    }

    public function rules(): array
    {
        $id = $this->route('payment_type')?->id ?? $this->route('payment_type');

        return [
            'category_id' => ['required', 'integer', 'exists:payment_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_types', 'name')
                    ->where(fn ($query) => $query->where('category_id', $this->input('category_id')))
                    ->ignore($id),
            ],
        ];
    }
}
