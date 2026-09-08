<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;

class UpdateProfileRequest extends ApiFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
