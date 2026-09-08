<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Auth\RegisterRequest;

class UpdatePasswordRequest extends ApiFormRequest
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
            'old_password' => ['required', 'string'],
            'password' => ['required', 'min:8', 'regex:'.RegisterRequest::PASSWORD_REGEX],
        ];
    }
}
