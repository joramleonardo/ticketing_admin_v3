@component('mail::message')
# Your Support Request Is In Progress

Hi **{{ $clientName }}**,

**{{ $staffName }}** from the DOST-STII IT Unit is now working on your support ticket.

@component('mail::panel')
**TICKET SUMMARY**

**Reference Number:** {{ $refNo }}

**Status:** In Progress

@if (!empty($ticketType))
**Request Type:** {{ $ticketType }}
@endif
@endcomponent

@if (!empty($problem))
## Your Concern

{{ $problem }}

---
@endif

## Stay Updated

Visit the client portal to track your ticket’s progress, view status updates, and communicate with your assigned IT specialist.

@component('mail::button', ['url' => $trackUrl, 'color' => 'blue'])
View Ticket Progress
@endcomponent

Thank you for your patience while we work on your request.

Best regards,
**DOST-STII IT Support Desk**

@component('mail::subcopy')
If the button does not work, copy and paste the following URL into your browser:
[{{ $trackUrl }}]({{ $trackUrl }})
@endcomponent
@endcomponent
