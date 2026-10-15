@component('mail::message')
# Your Support Request Has Been Received

Hi **{{ $clientName }}**,

Thank you for reaching out to the DOST-STII IT Unit. We have received your technical support request and logged it into our system.

@component('mail::panel')
**TICKET SUMMARY**

**Reference Number:** {{ $refNo }}

**Status:** New / Received

@if (!empty($ticketType))
**Request Type:** {{ $ticketType }}
@endif

@if (!empty($dateRequested))
**Date Requested:** {{ $dateRequested }}
@endif
@endcomponent

@if (!empty($problem))
## Concern Reported

{{ $problem }}

---
@endif

## What Happens Next?

Our IT Support specialists are reviewing your request. You will receive email updates in this thread as an IT staff member attends to your ticket, posts updates, and resolves your concern.

You can also monitor the ticket status and chat directly with your assigned technician using our online portal:

@component('mail::button', ['url' => $trackUrl, 'color' => 'blue'])
Track Your Ticket
@endcomponent

Best regards,
**DOST-STII IT Support Desk**

@component('mail::subcopy')
If you're having trouble clicking the "Track Your Ticket" button, copy and paste the following URL into your browser:
[{{ $trackUrl }}]({{ $trackUrl }})
@endcomponent
@endcomponent

