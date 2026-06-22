<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department?->id ?? $department;
        $officeId = $this->input('office_id', $department?->office_id);

        return [
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where('office_id', $officeId)->ignore($departmentId)],
        ];
    }
}
