<?php

namespace App\Rules;

use App\Services\CalendarService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCalendar implements ValidationRule
{
    private ?string $userId;

    public function __construct(?string $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a valid calendar ID.');
            return;
        }

        $calendarService = app(CalendarService::class);
        
        if (!$calendarService->validateCalendar($value, $this->userId)) {
            $fail('The selected calendar is invalid or not accessible.');
            return;
        }

        // Additional check: if userId is provided, verify user has access
        if ($this->userId && !$calendarService->userHasAccessToCalendar($this->userId, $value)) {
            $fail('You do not have access to the selected calendar.');
        }
    }
}
