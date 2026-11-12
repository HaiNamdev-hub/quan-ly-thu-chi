<?php

namespace App\Http\Requests;

use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9._-]+$/', 'max:50', Rule::unique('users', 'username')->ignore($this->route('user'))],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
