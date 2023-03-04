<?php

namespace App\Domain;

enum Weekday: string
{
    case MONDAY = 'Monday';
    case TUESDAY = 'Tuesday';
    case WEDNESDAY = 'Wednesday';
    case THURSDAY = 'Thursday';
    case FRIDAY = 'Friday';
    case SATURDAY = 'Saturday';
    case SUNDAY = 'Sunday';

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return self::from($dateTime->format('l'));
    }
}
