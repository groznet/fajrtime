<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    protected $errorBag = 'withdrawal';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Whole rubles.
            'amount' => ['required', 'integer', 'min:'.Setting::int('withdrawal_min') / 100],
            // Payout via СБП: phone number + bank. We never ask for card numbers.
            'phone' => ['required', 'string', 'regex:/^\+?[78][\s\-()]*(\d[\s\-()]*){10}$/'],
            'bank' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'сумма', 'phone' => 'телефон', 'bank' => 'банк'];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Укажите российский номер телефона, например +7 900 123-45-67.'];
    }

    public function amountKopecks(): int
    {
        return (int) $this->validated('amount') * 100;
    }

    /**
     * Phone normalized to +7XXXXXXXXXX.
     */
    public function details(): array
    {
        $digits = preg_replace('/\D/', '', $this->validated('phone'));

        return [
            'method' => 'sbp',
            'phone' => '+7'.substr($digits, -10),
            'bank' => trim($this->validated('bank')),
        ];
    }
}
