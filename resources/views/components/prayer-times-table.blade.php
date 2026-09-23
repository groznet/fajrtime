@props(['times', 'timezone'])

<table class="w-full text-left">
    <tbody class="divide-y divide-gray-100">
        @foreach (\App\Support\PrayerTimes::NAMES as $key => $label)
            <tr @class(['font-semibold text-emerald-800' => $key === 'fajr', 'text-gray-500' => $key === 'sunrise'])>
                <td class="py-2">{{ $label }}</td>
                <td class="py-2 text-right tabular-nums">{{ $times->local($key, $timezone) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
