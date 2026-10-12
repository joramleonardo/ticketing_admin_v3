<?php

namespace App\Enums;

enum SupportNature: string
{
    case Hardware = 'Hardware';
    case Software = 'Software';
    case HardwareAndSoftware = 'Hardware & Software';
    case SystemModification = 'System Modification';
    case Livestream = 'Livestream';
    case Events = 'Events';
    case Others = 'Others';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
