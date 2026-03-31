<?php

namespace App\Enums;

enum RoundPhase: string
{
    case QuarterFinals = 'quarter_finals';
    case SemiFinals = 'semi_finals';
    case ThirdPlace = 'third_place';
    case Finals = 'finals';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $phase): string => $phase->value,
            self::cases(),
        );
    }
}
