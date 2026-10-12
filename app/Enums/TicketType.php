<?php

namespace App\Enums;

enum TicketType: string
{
    case TechnicalSupport = 'Technical Support';
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
