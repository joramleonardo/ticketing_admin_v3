<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'New';
    case Pending = 'Pending';
    case InProgress = 'In Progress';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
    case Closed = 'Closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Active statuses representing open or in-flight tickets.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::New->value,
            self::Pending->value,
            self::InProgress->value,
        ];
    }

    /**
     * Terminal statuses representing finalized or voided tickets.
     *
     * @return array<int, string>
     */
    public static function terminalValues(): array
    {
        return [
            self::Cancelled->value,
            self::Closed->value,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Closed], true);
    }
}
