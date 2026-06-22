<?php

namespace App\Http\Requests\PaymentCategory;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentCategoryRequest extends FormRequest
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
        return $this->isSuperAdmin() || $this->user()?->can('payment.create') || $this->user()?->can('payment.update') || $this->user()?->can('payment.approve');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('payment_categories', 'name')],
        ];
    }
}
