<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Whole rubles.
            'stake' => ['required', 'integer', 'min:'.Setting::int('stake_min') / 100, 'max:'.Setting::int('stake_max') / 100],
            'cycle_days' => ['required', 'integer', Rule::in(config('fajr.cycle_days'))],
            'visibility' => ['required', Rule::in(['public', 'private'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'stake' => 'ставка',
            'cycle_days' => 'длительность',
            'visibility' => 'видимость',
        ];
    }

    /**
     * Attributes for the groups table.
     */
    public function groupData(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'stake_amount' => (int) $data['stake'] * 100,
            'cycle_days' => (int) $data['cycle_days'],
            'visibility' => $data['visibility'],
        ];
    }
}
