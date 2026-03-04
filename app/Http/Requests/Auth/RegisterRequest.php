<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'min:3', 'max:100'],
            'email'         => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password'      => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'phone'         => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\(\)\+\-]+$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'O nome é obrigatório.',
            'name.min'               => 'O nome deve ter pelo menos 3 caracteres.',
            'email.required'         => 'O e-mail é obrigatório.',
            'email.email'            => 'Informe um e-mail válido.',
            'email.unique'           => 'Este e-mail já está cadastrado.',
            'password.required'      => 'A senha é obrigatória.',
            'password.min'           => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed'     => 'As senhas não coincidem.',
            'password.regex'         => 'A senha deve conter maiúscula, minúscula, número e símbolo especial.',
            'phone.regex'            => 'Formato de telefone inválido.',
            'date_of_birth.before'   => 'A data de nascimento deve ser anterior a hoje.',
        ];
    }

    /**
     * Sanitiza os dados antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => strip_tags(trim($this->name ?? '')),
            'email' => strtolower(trim($this->email ?? '')),
            'phone' => preg_replace('/[^0-9\s\(\)\+\-]/', '', $this->phone ?? ''),
        ]);
    }
}
