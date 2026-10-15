@component('mail::message')
# Your Support Ticket Has Been Assigned

Hi **{{ $clientName }}**,

Your support ticket has been assigned to **{{ $staffName }}** from the DOST-STII IT Unit, who will handle your request.

@component('mail::panel')
**TICKET SUMMARY**

**Reference Number:** {{ $refNo }}

**Assigned Specialist:** {{ $staffName }}

**Status:** {{ $status ?? 'Pending / Assigned' }}

@if (!empty($ticketType))
**Request Type:** {{ $ticketType }}
@endif
@endcomponent

@if (!empty($problem))
## Your Concern

{{ $problem }}

---
@endif

## What Happens Next?

Our assigned specialist is reviewing your request and will attend to it shortly. You will receive further updates in this thread as work begins and progresses.

You can monitor the ticket status and chat directly with your assigned technician using our online portal:

@component('mail::button', ['url' => $trackUrl, 'color' => 'blue'])
Track Your Ticket
@endcomponent

Thank you for your patience.

Best regards,
**DOST-STII IT Support Desk**

@component('mail::subcopy')
If you're having trouble clicking the "Track Your Ticket" button, copy and paste the following URL into your browser:
[{{ $trackUrl }}]({{ $trackUrl }})
@endcomponent
@endcomponent

