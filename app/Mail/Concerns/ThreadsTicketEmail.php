<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

trait ThreadsTicketEmail
{
    /**
     * Get the root message ID for this ticket thread.
     */
    public function getTicketRootMessageId(): string
    {
        $domain = $this->resolveMailDomain();

        return "ticket-{$this->refNo}@{$domain}";
    }

    /**
     * Resolve the domain to use in message IDs.
     */
    public function resolveMailDomain(): string
    {
        return (string) config('mail.thread_domain', 'ticketing.stii.local');
    }

    /**
     * Build the unified subject line for the ticket conversation thread.
     */
    public function buildThreadSubject(?string $ticketType = null, ?string $problem = null, bool $isReply = true): string
    {
        $topic = $problem ? Str::limit(trim(preg_replace('/\s+/', ' ', $problem)), 50) : ($ticketType ?: 'IT Support Request');
        $base = "[Ticket #{$this->refNo}] IT Support: {$topic}";

        return $isReply ? "Re: {$base}" : $base;
    }

    /**
     * Generate RFC-compliant threading headers.
     */
    public function threadHeaders(bool $isRoot = false): Headers
    {
        $rootId = $this->getTicketRootMessageId();
        $domain = $this->resolveMailDomain();

        if ($isRoot) {
            return new Headers(
                messageId: $rootId,
            );
        }

        return new Headers(
            messageId: "ticket-{$this->refNo}-".bin2hex(random_bytes(6))."@{$domain}",
            references: [$rootId],
            text: [
                'In-Reply-To' => "<{$rootId}>",
            ],
        );
    }
}
