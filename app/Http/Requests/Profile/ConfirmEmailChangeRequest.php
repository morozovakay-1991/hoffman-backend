<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;

class ConfirmEmailChangeRequest extends ApiFormRequest
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
            'code' => ['required', 'string'],
        ];
    }
}
