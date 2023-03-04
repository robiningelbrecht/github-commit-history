<?php

namespace App\Domain;

enum DayTime: string
{
    case MORNING = 'Morning';
    case DAYTIME = 'Daytime';
    case EVENING = 'Evening';
    case NIGHT = 'Night';

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        $hour = (int) $dateTime->format('G');

        return match ((int) floor($hour / 6)) {
            1 => self::MORNING, //  6 - 12
            2 => self::DAYTIME, // 12 - 18
            3 => self::EVENING, //  18 - 24
            0 => self::NIGHT, // 0 - 6
        };
    }

    public function getEmoji(): string
    {
        return match ($this) {
            self::MORNING => '🌞',
            self::DAYTIME => '🌆',
            self::EVENING => '🌃',
            self::NIGHT => '🌙',
        };
    }
}
