<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    /**
     * Turn a failed validation into the project's unified error response.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $this->errorCode($validator),
                'message' => $this->errorMessage($validator),
                'fields' => $validator->errors()->toArray(),
            ],
        ], 422));
    }

    /**
     * Allow subclasses to map specific rule failures onto a more specific error code.
     */
    protected function errorCode(Validator $validator): string
    {
        return 'VALIDATION_ERROR';
    }

    /**
     * Allow subclasses to customize the top-level error message per error code.
     */
    protected function errorMessage(Validator $validator): string
    {
        return 'The given data was invalid.';
    }
}
