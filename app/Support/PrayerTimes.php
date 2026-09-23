<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Prayer times for one city and one local date. All times are UTC.
 * Fajr and Isha are null when the sun never reaches the required angle (high latitudes).
 */
final readonly class PrayerTimes
{
    public const NAMES = [
        'fajr' => 'Фаджр',
        'sunrise' => 'Восход',
        'dhuhr' => 'Зухр',
        'asr' => 'Аср',
        'maghrib' => 'Магриб',
        'isha' => 'Иша',
    ];

    public function __construct(
        public string $date,
        public ?CarbonImmutable $fajr,
        public CarbonImmutable $sunrise,
        public CarbonImmutable $dhuhr,
        public CarbonImmutable $asr,
        public CarbonImmutable $maghrib,
        public ?CarbonImmutable $isha,
    ) {}

    public function get(string $name): ?CarbonImmutable
    {
        return $this->{$name};
    }

    /**
     * Time formatted as H:i in the given timezone, or a dash if it cannot be calculated.
     */
    public function local(string $name, string $timezone): string
    {
        return $this->get($name)?->setTimezone($timezone)->format('H:i') ?? '—';
    }

    public function toArray(): array
    {
        $times = ['date' => $this->date];

        foreach (array_keys(self::NAMES) as $name) {
            $times[$name] = $this->get($name)?->toIso8601String();
        }

        return $times;
    }

    public static function fromArray(array $data): self
    {
        $parse = fn (?string $value) => $value === null ? null : CarbonImmutable::parse($value)->utc();

        return new self(
            $data['date'],
            $parse($data['fajr']),
            $parse($data['sunrise']),
            $parse($data['dhuhr']),
            $parse($data['asr']),
            $parse($data['maghrib']),
            $parse($data['isha']),
        );
    }
}
