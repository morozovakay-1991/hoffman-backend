<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;

class UpdateNotificationsRequest extends ApiFormRequest
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
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'marketing_enabled' => ['sometimes', 'boolean'],
            'daily_practices_enabled' => ['sometimes', 'boolean'],
            'new_articles_enabled' => ['sometimes', 'boolean'],
            'system_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
