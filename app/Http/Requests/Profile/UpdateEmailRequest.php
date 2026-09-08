<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends ApiFormRequest
{
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
            'new_email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
        ];
    }

    protected function errorCode(Validator $validator): string
    {
        return isset($validator->failed()['new_email']['Unique'])
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
