<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recovery_email' => $this->user()->role === 'admin'
                ? ['sometimes', 'nullable', 'email', 'max:255']
                : ['prohibited'],
            'recovery_password' => $this->user()->role === 'admin'
                && $this->exists('recovery_email')
                && $this->input('recovery_email') !== $this->user()->recovery_email
                    ? ['required', 'current_password']
                    : ['sometimes', 'nullable'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
