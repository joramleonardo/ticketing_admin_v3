<?php

namespace App\Support;

use App\Enums\SupportNature;
use App\Enums\TicketType;

class TicketClassification
{
    /**
     * @return array<int, string>
     */
    public static function ticketTypes(): array
    {
        return TicketType::values();
    }

    /**
     * @return array<int, string>
     */
    public static function natureOptions(): array
    {
        return SupportNature::values();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function subcategories(): array
    {
        return [
            'Hardware' => ['PC', 'Printer', 'Peripherals', 'Others'],
            'Software' => ['Install', 'Configure', 'Troubleshoot', 'Others'],
            'Hardware & Software' => ['Driver', 'Firmware', 'Compatibility', 'Others'],
            'System Modification' => [],
            'Events' => [],
            'Livestream' => [],
            'Others' => ['Others'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function relatedTypes(): array
    {
        return [
            'Installation related',
            'Livestream related',
            'Network related',
            'PC Setup & Troubleshooting',
            'Printer related',
            'Server Related',
            'STARBOOKS related',
            'Website and System related',
            'Events related',
            'Others',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function remarksOptions(): array
    {
        return [
            'N/A',
            'Issue Resolved Upon Arrival',
            'No Issue Found',
            'Duplicate Ticket',
            'Request No Longer Needed',
            'Ticket Cancelled',
            'Ticket Rescheduled',
            'Outside the Scope of Support',
            'Others',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function divisions(): array
    {
        return [
            1 => 'FAD',
            2 => 'IRAD',
            3 => 'CRPD',
            4 => 'OD-MISPS',
            'OTHERS' => 'OTHERS',
        ];
    }

    public static function mapDivision(?string $divisionId): string
    {
        return match ((string) $divisionId) {
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
            default => 'OTHERS',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'ticket_types' => self::ticketTypes(),
            'nature_options' => self::natureOptions(),
            'nature_of_support' => self::natureOptions(),
            'subcategories' => self::subcategories(),
            'related_types' => self::relatedTypes(),
            'remarks_options' => self::remarksOptions(),
            'divisions' => self::divisions(),
        ];
    }
}
