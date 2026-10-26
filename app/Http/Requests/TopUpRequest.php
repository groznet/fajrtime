<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Whole rubles.
            'amount' => ['required', 'integer', 'min:'.Setting::int('topup_min') / 100, 'max:'.Setting::int('topup_max') / 100],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'сумма'];
    }

    public function amountKopecks(): int
    {
        return (int) $this->validated('amount') * 100;
    }
}
