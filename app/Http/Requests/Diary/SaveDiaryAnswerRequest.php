<?php

namespace App\Http\Requests\Diary;

use App\Http\Requests\ApiFormRequest;

class SaveDiaryAnswerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pull the device timezone out of the X-Timezone header (rather than the
     * body) so it validates alongside the rest of the payload.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'timezone' => $this->header('X-Timezone'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string'],
            'timezone' => ['required', 'string', 'timezone'],
        ];
    }
}
