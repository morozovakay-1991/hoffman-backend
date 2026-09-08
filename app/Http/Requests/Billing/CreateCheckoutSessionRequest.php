<?php

namespace App\Http\Requests\Billing;

use App\Domain\Billing\Support\PlanCatalog;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutSessionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => is_string($this->currency) ? strtoupper($this->currency) : $this->currency,
            'country' => is_string($this->country) ? strtoupper($this->country) : $this->country,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(PlanCatalog::ids())],
            'currency' => ['required', 'string', Rule::in(config('billing.currencies', []))],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
        ];
    }
}
