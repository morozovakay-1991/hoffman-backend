<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiFormRequest
{
    public const PASSWORD_REGEX = '/^(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'regex:'.self::PASSWORD_REGEX],
        ];
    }

    protected function errorCode(Validator $validator): string
    {
        return isset($validator->failed()['email']['Unique'])
            ? 'EMAIL_TAKEN'
            : 'VALIDATION_ERROR';
    }

    protected function errorMessage(Validator $validator): string
    {
        return $this->errorCode($validator) === 'EMAIL_TAKEN'
            ? 'This email address is already registered.'
            : 'The given data was invalid.';
    }
}
