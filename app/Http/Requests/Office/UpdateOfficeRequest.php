<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        $officeId = $this->route('office')?->id ?? $this->route('office');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')->ignore($officeId)],
        ];
    }
}
