<?php

namespace App\Enums;

enum CheckInResult: string
{
    case Confirmed = 'confirmed';
    case Wrong = 'wrong';
    case TooEarly = 'too_early';
    case Expired = 'expired';
    case Locked = 'locked';
    case Already = 'already';
    case None = 'none';

    public function message(): string
    {
        return match ($this) {
            self::Confirmed => 'Подъём подтверждён. Да примет Аллах ваш намаз!',
            self::Wrong => 'Неверный код.',
            self::TooEarly => 'Код ещё не появился. Дождитесь его на этой странице.',
            self::Expired => 'Время на ввод кода вышло.',
            self::Locked => 'Слишком много неверных попыток на сегодня.',
            self::Already => 'Сегодняшний подъём уже подтверждён.',
            self::None => 'Сейчас нет проверки, которую нужно подтвердить.',
        };
    }
}
