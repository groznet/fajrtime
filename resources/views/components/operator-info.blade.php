@php($op = config('fajr.operator'))
<dl {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-[14rem_1fr] gap-x-6 gap-y-2']) }}>
    <dt class="text-gray-500">Владелец сайта</dt>
    <dd>{{ $op['legal_name'] ?: $op['name'] }}</dd>
    @if ($op['tax_id'])
        <dt class="text-gray-500">ИНН</dt>
        <dd>{{ $op['tax_id'] }}</dd>
    @endif
    @if ($op['reg_number'])
        <dt class="text-gray-500">ОГРН / ОГРНИП</dt>
        <dd>{{ $op['reg_number'] }}</dd>
    @endif
    <dt class="text-gray-500">Место нахождения и адрес</dt>
    <dd>{{ $op['address'] }}</dd>
    @if ($op['phone'])
        <dt class="text-gray-500">Телефон</dt>
        <dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $op['phone']) }}" class="underline text-emerald-800">{{ $op['phone'] }}</a></dd>
    @endif
    <dt class="text-gray-500">Email</dt>
    <dd><a href="mailto:{{ $op['email'] }}" class="underline text-emerald-800">{{ $op['email'] }}</a></dd>
    <dt class="text-gray-500">Сайт</dt>
    <dd>{{ $op['domain'] }}</dd>
</dl>
