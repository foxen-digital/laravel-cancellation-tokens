<?php

namespace Foxen\CancellationToken\Rules;

use Closure;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCancellationToken implements ValidationRule
{
    public ?TokenVerificationFailure $failureReason = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->failureReason = null;

        if (! is_string($value)) {
            $fail('cancellation-tokens::validation.cancellation_token')->translate();

            return;
        }

        try {
            app(CancellationTokenContract::class)->verify($value);
        } catch (TokenVerificationException $e) {
            $this->failureReason = $e->reason;
            $fail('cancellation-tokens::validation.cancellation_token')->translate();
        }
    }
}
