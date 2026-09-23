@props(['options' => [], 'selected' => null, 'placeholder' => null])

<select {{ $attributes->merge(['class' => 'border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md']) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
